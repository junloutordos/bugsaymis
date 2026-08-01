<?php

namespace App\Services\ScienceLab;

use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Office;
use App\Models\ScienceLab\LabEquipmentRequest;
use App\Models\ScienceLab\LabReagentRequest;
use App\Models\ScienceLab\LabRequestBundle;
use App\Models\ScienceLab\LabRequestStatusHistory;
use App\Models\ScienceLab\LabReservation;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabRequestWorkflowService
{
    public function __construct(private LabControlNumberService $controlNumbers) {}

    public function requesterType(User $user): string
    {
        if ($user->hasRole('Student') || $user->account_type === 'student') {
            return 'student';
        }

        return Office::where('unit_head', $user->id)->exists() ? 'unit_head' : 'teacher';
    }

    public function endorserOptions(User $user)
    {
        $type = $this->requesterType($user);
        if ($type === 'student' || $user->isSuperAdmin()) {
            return User::havingRole('Faculty')->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'position']);
        }

        $endorser = $type === 'unit_head'
            ? $this->acidaaHolder()
            : Office::with('unitHeadUser:id,name,position')->find($user->office_id)?->unitHeadUser;

        return $endorser ? collect([$endorser->only('id', 'name', 'position')]) : collect();
    }

    public function hasOutstandingCsm(User $user): bool
    {
        return LabRequestBundle::where('requested_by_id', $user->id)
            ->whereNotNull('completed_at')->whereNull('csm_completed_at')->exists();
    }

    public function createForUser(User $user, array $data): LabRequestBundle
    {
        if ($this->hasOutstandingCsm($user)) {
            throw ValidationException::withMessages([
                'csm' => 'Complete the Client Satisfaction Survey for your previous laboratory request before filing another.',
            ]);
        }

        $requesterType = $this->requesterType($user);
        $endorser = $this->resolveEndorser($user, $requesterType, $data['assigned_endorser_id'] ?? null);
        $approver = ScienceLabProfile::where('room_id', $data['room_id'])
            ->where('status', 'active')->value('in_charge_user_id');
        if (! $approver) {
            throw ValidationException::withMessages(['room_id' => 'The selected laboratory has no assigned SRA/SRS approver.']);
        }

        return DB::transaction(function () use ($user, $data, $requesterType, $endorser, $approver) {
            $schoolYearId = SchoolYear::where('is_current', true)->value('id');
            $bundle = LabRequestBundle::create([
                'reference_no' => $this->controlNumbers->next(LabControlNumberService::BUNDLE, 'lab_request_bundles', 'reference_no'),
                'school_year_id' => $schoolYearId,
                'requested_by_id' => $user->id,
                'requester_name' => $user->name,
                'requester_type' => $requesterType,
                'assigned_endorser_id' => $endorser->id,
                'assigned_approver_id' => $approver,
                'status' => 'pending_endorsement',
                'submitted_at' => now(),
            ]);

            $common = Arr::only($data, [
                'grade_level_section', 'number_of_students', 'subject', 'teacher_in_charge',
                'date_start', 'date_end', 'time_start', 'time_end', 'student_group', 'remarks',
            ]);
            $common += [
                'bundle_id' => $bundle->id,
                'school_year_id' => $schoolYearId,
                'requested_by_id' => $user->id,
                'requester_name' => $user->name,
                'requester_type' => $requesterType,
                'status' => 'pending',
            ];

            $reservation = LabReservation::create($common + [
                'control_no' => $this->controlNumbers->next(LabControlNumberService::RESERVATION, 'lab_reservations'),
                'room_id' => $data['room_id'],
            ]);

            if ($data['needs_equipment'] ?? false) {
                $equipment = LabEquipmentRequest::create($common + [
                    'control_no' => $this->controlNumbers->next(LabControlNumberService::EQUIPMENT, 'lab_equipment_requests'),
                    'reservation_id' => $reservation->id,
                    'concurrent_topic' => $data['concurrent_topic'] ?? null,
                    'unit' => $data['unit'] ?? null,
                    'venue' => $data['venue'] ?? null,
                ]);
                foreach ($data['equipment_items'] ?? [] as $item) {
                    $equipment->items()->create($item);
                }
            }

            if ($data['needs_reagents'] ?? false) {
                $reagent = LabReagentRequest::create($common + [
                    'control_no' => $this->controlNumbers->next(LabControlNumberService::REAGENT, 'lab_reagent_requests'),
                    'reservation_id' => $reservation->id,
                    'concurrent_topic' => $data['concurrent_topic'] ?? null,
                    'unit' => $data['unit'] ?? null,
                    'venue' => $data['venue'] ?? null,
                    'sds_acknowledged' => true,
                ]);
                foreach ($data['reagent_items'] ?? [] as $item) {
                    $reagent->items()->create($item);
                }
            }

            $this->history($bundle, null, 'pending_endorsement', $user, 'Request package submitted.');

            return $bundle->load(['reservation', 'equipmentRequest.items', 'reagentRequest.items']);
        });
    }

    public function endorse(LabRequestBundle $bundle, User $actor): void
    {
        abort_unless($actor->isSuperAdmin() || $bundle->assigned_endorser_id === $actor->id, 403);
        abort_unless($bundle->status === 'pending_endorsement', 422, 'This request is not pending endorsement.');

        DB::transaction(function () use ($bundle, $actor) {
            foreach ($this->forms($bundle) as $form) {
                $form->update(['endorsed_by_id' => $actor->id, 'endorsed_at' => now(), 'status' => 'endorsed']);
            }
            $bundle->update(['status' => 'pending_approval']);
            $this->history($bundle, 'pending_endorsement', 'pending_approval', $actor, 'Request package endorsed.');
        });
    }

    public function updateForUser(LabRequestBundle $bundle, User $user, array $data): void
    {
        abort_unless($user->isSuperAdmin() || $bundle->requested_by_id === $user->id, 403);
        abort_unless($bundle->status === 'pending_endorsement', 422, 'Only requests awaiting endorsement can be edited.');

        $requesterType = $this->requesterType($user);
        $endorser = $this->resolveEndorser($user, $requesterType, $data['assigned_endorser_id'] ?? null);
        $approver = ScienceLabProfile::where('room_id', $data['room_id'])
            ->where('status', 'active')->value('in_charge_user_id');
        if (! $approver) {
            throw ValidationException::withMessages(['room_id' => 'The selected laboratory has no assigned SRA/SRS approver.']);
        }

        DB::transaction(function () use ($bundle, $user, $data, $endorser, $approver) {
            $bundle->load(['reservation', 'equipmentRequest.items', 'reagentRequest.items']);
            $bundle->update(['assigned_endorser_id' => $endorser->id, 'assigned_approver_id' => $approver]);
            $common = Arr::only($data, [
                'grade_level_section', 'number_of_students', 'subject', 'teacher_in_charge',
                'date_start', 'date_end', 'time_start', 'time_end', 'student_group', 'remarks',
            ]);
            $bundle->reservation->update($common + ['room_id' => $data['room_id']]);

            if ($data['needs_equipment'] ?? false) {
                $equipment = $bundle->equipmentRequest ?: LabEquipmentRequest::create($common + [
                    'bundle_id' => $bundle->id,
                    'control_no' => $this->controlNumbers->next(LabControlNumberService::EQUIPMENT, 'lab_equipment_requests'),
                    'school_year_id' => $bundle->school_year_id,
                    'reservation_id' => $bundle->reservation->id,
                    'requested_by_id' => $bundle->requested_by_id,
                    'requester_name' => $bundle->requester_name,
                    'requester_type' => $bundle->requester_type,
                    'status' => 'pending',
                ]);
                $equipment->update($common + Arr::only($data, ['concurrent_topic', 'unit', 'venue']));
                $equipment->items()->delete();
                foreach ($data['equipment_items'] ?? [] as $item) $equipment->items()->create($item);
            } elseif ($bundle->equipmentRequest) {
                $bundle->equipmentRequest->delete();
            }

            if ($data['needs_reagents'] ?? false) {
                $reagent = $bundle->reagentRequest ?: LabReagentRequest::create($common + [
                    'bundle_id' => $bundle->id,
                    'control_no' => $this->controlNumbers->next(LabControlNumberService::REAGENT, 'lab_reagent_requests'),
                    'school_year_id' => $bundle->school_year_id,
                    'reservation_id' => $bundle->reservation->id,
                    'requested_by_id' => $bundle->requested_by_id,
                    'requester_name' => $bundle->requester_name,
                    'requester_type' => $bundle->requester_type,
                    'sds_acknowledged' => true,
                    'status' => 'pending',
                ]);
                $reagent->update($common + Arr::only($data, ['concurrent_topic', 'unit', 'venue']));
                $reagent->items()->delete();
                foreach ($data['reagent_items'] ?? [] as $item) $reagent->items()->create($item);
            } elseif ($bundle->reagentRequest) {
                $bundle->reagentRequest->delete();
            }

            $this->history($bundle, 'pending_endorsement', 'pending_endorsement', $user, 'Request package edited.');
        });
    }

    public function approve(LabRequestBundle $bundle, User $actor): void
    {
        abort_unless($actor->isSuperAdmin() || $bundle->assigned_approver_id === $actor->id, 403);
        abort_unless($bundle->status === 'pending_approval', 422, 'This request is not pending SRA/SRS approval.');

        DB::transaction(function () use ($bundle, $actor) {
            foreach ($this->forms($bundle) as $form) {
                $form->update(['approved_by_id' => $actor->id, 'approved_at' => now(), 'status' => 'approved']);
            }
            $bundle->update(['status' => 'approved']);
            $this->history($bundle, 'pending_approval', 'approved', $actor, 'Request package approved by the concerned SRA/SRS.');
        });
    }

    public function cancel(LabRequestBundle $bundle, User $actor, string $reason): void
    {
        abort_unless($actor->isSuperAdmin() || $actor->hasPermission('lab.manage') || $bundle->requested_by_id === $actor->id, 403);
        abort_if(in_array($bundle->status, ['ongoing', 'completed', 'cancelled']), 422, 'This request can no longer be cancelled.');

        DB::transaction(function () use ($bundle, $actor, $reason) {
            $from = $bundle->status;
            foreach ($this->forms($bundle) as $form) {
                $form->update(['status' => 'cancelled']);
            }
            $bundle->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
            $this->history($bundle, $from, 'cancelled', $actor, $reason);
        });
    }

    public function decline(LabRequestBundle $bundle, User $actor, string $reason): void
    {
        $allowed = $actor->isSuperAdmin() || $actor->hasPermission('lab.manage')
            || $bundle->assigned_endorser_id === $actor->id || $bundle->assigned_approver_id === $actor->id;
        abort_unless($allowed, 403);
        abort_unless(in_array($bundle->status, ['pending_endorsement', 'pending_approval']), 422, 'This request can no longer be declined.');

        DB::transaction(function () use ($bundle, $actor, $reason) {
            $from = $bundle->status;
            foreach ($this->forms($bundle) as $form) {
                $form->update(['status' => 'declined', 'decline_reason' => $reason]);
            }
            $bundle->update(['status' => 'declined']);
            $this->history($bundle, $from, 'declined', $actor, $reason);
        });
    }

    public function syncCompletion(LabRequestBundle $bundle, User $actor): void
    {
        $bundle->loadMissing(['reservation', 'equipmentRequest', 'reagentRequest']);
        $equipmentDone = ! $bundle->equipmentRequest || in_array($bundle->equipmentRequest->status, ['returned', 'completed']);
        $reagentDone = ! $bundle->reagentRequest || in_array($bundle->reagentRequest->status, ['released', 'completed']);
        if (! $equipmentDone || ! $reagentDone) {
            if ($bundle->status !== 'ongoing') {
                $from = $bundle->status;
                $bundle->update(['status' => 'ongoing']);
                $this->history($bundle, $from, 'ongoing', $actor, 'Laboratory items released for use.');
            }
            return;
        }

        $from = $bundle->status;
        $bundle->reservation?->update(['status' => 'completed']);
        $bundle->equipmentRequest?->update(['status' => 'completed']);
        $bundle->reagentRequest?->update(['status' => 'completed']);
        $bundle->update(['status' => 'completed', 'completed_at' => now()]);
        $this->history($bundle, $from, 'completed', $actor, 'All requested services were completed. CSM is now required.');
    }

    public function completeReservationOnly(LabRequestBundle $bundle, User $actor): void
    {
        abort_unless($actor->isSuperAdmin() || $actor->hasPermission('lab.manage') || $bundle->assigned_approver_id === $actor->id, 403);
        $bundle->loadMissing(['reservation', 'equipmentRequest', 'reagentRequest']);
        abort_unless($bundle->status === 'approved' && ! $bundle->equipmentRequest && ! $bundle->reagentRequest, 422,
            'Requests with equipment or reagents are completed through their release and inspection actions.');
        $from = $bundle->status;
        $bundle->reservation?->update(['status' => 'completed']);
        $bundle->update(['status' => 'completed', 'completed_at' => now()]);
        $this->history($bundle, $from, 'completed', $actor, 'Laboratory reservation completed. CSM is now required.');
    }

    public function history(LabRequestBundle $bundle, ?string $from, string $to, ?User $actor, ?string $remarks = null): void
    {
        LabRequestStatusHistory::create([
            'bundle_id' => $bundle->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->id,
            'remarks' => $remarks,
        ]);
    }

    private function forms(LabRequestBundle $bundle): array
    {
        $bundle->loadMissing(['reservation', 'equipmentRequest', 'reagentRequest']);
        return array_values(array_filter([$bundle->reservation, $bundle->equipmentRequest, $bundle->reagentRequest]));
    }

    private function resolveEndorser(User $user, string $requesterType, ?int $selectedId): User
    {
        if ($requesterType === 'student' || $user->isSuperAdmin()) {
            $endorser = User::havingRole('Faculty')->where('status', '<>', 'inactive')->find($selectedId);
        } elseif ($requesterType === 'unit_head') {
            $endorser = $this->acidaaHolder();
        } else {
            $endorser = Office::find($user->office_id)?->unitHeadUser;
        }

        if (! $endorser) {
            throw ValidationException::withMessages([
                'assigned_endorser_id' => 'No valid endorser is configured for this requester. Contact the CID office.',
            ]);
        }

        return $endorser;
    }

    private function acidaaHolder(): ?User
    {
        $schoolYearId = SchoolYear::where('is_current', true)->value('id');
        $designationIds = Designation::whereIn('code', ['ACIDAA', 'SUP-ACIDAA'])
            ->orWhere('name', 'like', 'Assistant CID Chief for Academic Affairs%')->pluck('id');
        $userId = LoadAssignment::whereIn('designation_id', $designationIds)
            ->when($schoolYearId, fn ($q) => $q->where('school_year_id', $schoolYearId))
            ->latest('id')->value('user_id');

        return $userId ? User::where('status', '<>', 'inactive')->find($userId) : null;
    }
}
