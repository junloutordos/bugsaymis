<?php

namespace App\Services\HR;

use App\Models\HR\ServiceRecordEntry;
use App\Models\HR\ServiceRecordEvidence;
use App\Models\HR\ServiceRecordVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRecordService
{
    public function save(User $employee, array $data, User $actor, ?ServiceRecordEntry $entry = null): ServiceRecordEntry
    {
        return DB::transaction(function () use ($employee, $data, $actor, $entry) {
            $lwop = $data['lwop_periods'] ?? [];
            unset($data['lwop_periods']);

            if ($entry) {
                abort_unless((int) $entry->user_id === (int) $employee->id, 404);
                $entry->fill([
                    ...$data,
                    'status' => 'draft',
                    'verified_by' => null,
                    'verified_at' => null,
                    'updated_by' => $actor->id,
                ])->save();
                $action = 'updated';
            } else {
                $entry = ServiceRecordEntry::create([
                    ...$data,
                    'user_id' => $employee->id,
                    'source_type' => 'manual',
                    'status' => 'draft',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $action = 'created';
            }

            $entry->lwopPeriods()->delete();
            foreach ($lwop as $period) {
                $entry->lwopPeriods()->create($period);
            }

            AuditLogger::logModelEvent($entry, $action);

            return $entry->fresh(['lwopPeriods', 'evidence', 'verifier:id,name']);
        });
    }

    public function verify(ServiceRecordEntry $entry, User $actor): ServiceRecordEntry
    {
        $entry->loadMissing('evidence');
        $errors = [];

        if ($entry->credited_as_government_service && ! $entry->assumed_at) {
            $errors['assumed_at'] = 'Actual assumption-to-duty date is required before crediting government service.';
        }
        if ($entry->credited_as_government_service && $entry->assumed_at?->toDateString() !== $entry->service_from?->toDateString()) {
            $errors['service_from'] = 'The first credited service date must match the actual assumption-to-duty date.';
        }
        if ($entry->credited_as_government_service && (! $entry->employment_status || ! $entry->salary_amount || ! $entry->salary_basis)) {
            $errors['appointment'] = 'Employment status and historical salary amount/basis are required before verification.';
        }
        if ($entry->credited_as_government_service) {
            $evidenceTypes = $entry->evidence->pluck('document_type');
            $hasPriorRecord = $evidenceTypes->contains('prior_service_record');
            if (! $hasPriorRecord && ! $evidenceTypes->contains('appointment')) {
                $errors['evidence'] = 'An appointment paper or certified prior-agency Service Record is required.';
            }
            if (! $hasPriorRecord && ! $evidenceTypes->contains('assumption_to_duty')) {
                $errors['assumption_evidence'] = 'Certification of Assumption to Duty is required for an agency appointment.';
            }
        }
        if (in_array($entry->employment_status, ['job_order', 'contract_of_service'], true)
            && $entry->credited_as_government_service) {
            $errors['credited_as_government_service'] = 'COS/JO engagements cannot be credited as government service.';
        }

        if ($entry->credited_as_government_service) {
            $overlap = ServiceRecordEntry::query()
                ->where('user_id', $entry->user_id)
                ->whereKeyNot($entry->id)
                ->verified()->creditable()
                ->where('service_from', '<=', $entry->service_to ?? '9999-12-31')
                ->where(fn ($query) => $query
                    ->whereNull('service_to')
                    ->orWhere('service_to', '>=', $entry->service_from))
                ->exists();

            if ($overlap) {
                $errors['service_from'] = 'This period overlaps another verified, creditable service entry.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $entry->update([
            'status' => 'verified',
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'updated_by' => $actor->id,
        ]);
        AuditLogger::logModelEvent($entry, 'updated');

        return $entry->fresh(['lwopPeriods', 'evidence', 'verifier:id,name']);
    }

    public function importPds(User $employee, User $actor): int
    {
        $employee->loadMissing('pds.workExperience');
        $created = 0;

        DB::transaction(function () use ($employee, $actor, &$created) {
            foreach ($employee->pds?->workExperience ?? [] as $row) {
                $exists = ServiceRecordEntry::where('user_id', $employee->id)
                    ->where('source_type', 'pds')
                    ->whereDate('service_from', $row->from_date)
                    ->where('position_title', $row->position)
                    ->where('agency_name', $row->agency)
                    ->exists();

                if ($exists || ! $row->from_date) {
                    continue;
                }

                $governmentService = filter_var($row->government_service, FILTER_VALIDATE_BOOLEAN)
                    || in_array(strtolower((string) $row->government_service), ['yes', 'y'], true);
                $status = Str::of((string) $row->appointment_status)->lower()->replace([' ', '-'], '_')->toString();
                $nonCreditable = in_array($status, ['job_order', 'contract_of_service', 'cos'], true);

                ServiceRecordEntry::create([
                    'user_id' => $employee->id,
                    'service_from' => $row->from_date,
                    'service_to' => $row->to_date,
                    'position_title' => $row->position,
                    'employment_status' => $status === 'cos' ? 'contract_of_service' : ($status ?: null),
                    'agency_name' => $row->agency,
                    'credited_as_government_service' => $governmentService && ! $nonCreditable,
                    'source_type' => 'pds',
                    'status' => 'draft',
                    'remarks' => 'Imported from employee-declared PDS work experience. Requires HR verification and documentary support.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                $created++;
            }
        });

        return $created;
    }

    public function storeEvidence(ServiceRecordEntry $entry, array $data, User $actor): ServiceRecordEvidence
    {
        if (! preg_match('/^data:([^;]+);base64,(.+)$/s', $data['data_uri'], $matches)) {
            throw ValidationException::withMessages(['data_uri' => 'The supporting document is not a valid base64 file.']);
        }

        $bytes = base64_decode($matches[2], true);
        $mime = $matches[1];
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages(['data_uri' => 'Only PDF, JPG, and PNG evidence is allowed.']),
        };

        if ($bytes === false || strlen($bytes) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['data_uri' => 'The file is invalid or exceeds 10 MB.']);
        }
        if ((new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime) {
            throw ValidationException::withMessages(['data_uri' => 'The file contents do not match the declared file type.']);
        }

        $path = "hr/service-records/{$entry->user_id}/entries/{$entry->id}/".Str::uuid().".{$extension}";
        Storage::disk('s3')->put($path, $bytes, ['ContentType' => $mime]);

        return $entry->evidence()->create([
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            's3_path' => $path,
            'original_filename' => basename(str_replace('\\', '/', preg_replace('/[\x00-\x1F\x7F]/u', '', $data['filename']))),
            'mime_type' => $mime,
            'file_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'uploaded_by' => $actor->id,
        ]);
    }

    public function diagnostics(Collection $entries): array
    {
        $ordered = $entries->sortBy('service_from')->values();
        $warnings = [];
        $creditedDays = 0;
        $lwopDays = 0.0;
        $previous = null;

        foreach ($ordered as $entry) {
            if ($entry->status !== 'verified') {
                $warnings[] = ['type' => 'draft', 'entry_id' => $entry->id, 'message' => "{$entry->position_title} is awaiting verification."];
            }
            if ($entry->credited_as_government_service && ! $entry->assumed_at) {
                $warnings[] = ['type' => 'missing_assumption', 'entry_id' => $entry->id, 'message' => "{$entry->position_title} has no assumption-to-duty date."];
            }
            if ($entry->evidence->isEmpty()) {
                $warnings[] = ['type' => 'missing_evidence', 'entry_id' => $entry->id, 'message' => "{$entry->position_title} has no supporting document."];
            }

            if ($entry->status === 'verified' && $entry->credited_as_government_service) {
                $end = $entry->service_to ? Carbon::parse($entry->service_to) : today();
                $creditedDays += Carbon::parse($entry->service_from)->diffInDays($end) + 1;
                $entryLwop = (float) $entry->lwopPeriods->sum(fn ($period) => $period->days
                    ?? (Carbon::parse($period->date_from)->diffInDays(Carbon::parse($period->date_to)) + 1));
                $lwopDays += $entryLwop;

                if ($previous) {
                    $previousEnd = $previous->service_to ? Carbon::parse($previous->service_to) : today();
                    if (Carbon::parse($entry->service_from)->lte($previousEnd)) {
                        $warnings[] = ['type' => 'overlap', 'entry_id' => $entry->id, 'message' => 'A verified creditable period overlaps an earlier entry.'];
                    } elseif ($previousEnd->copy()->addDay()->lt(Carbon::parse($entry->service_from))) {
                        $warnings[] = ['type' => 'gap', 'entry_id' => $entry->id, 'message' => 'There is a gap before this creditable service period.'];
                    }
                }
                $previous = $entry;
            }
        }

        return [
            'verified_entries' => $entries->where('status', 'verified')->count(),
            'draft_entries' => $entries->where('status', 'draft')->count(),
            'credited_calendar_days' => max(0, $creditedDays - (int) ceil($lwopDays)),
            'lwop_days' => $lwopDays,
            'warnings' => $warnings,
            'quality_score' => $entries->isEmpty() ? 0 : max(0, 100 - min(100, count($warnings) * 12)),
        ];
    }

    public function snapshot(User $employee): array
    {
        $employee->loadMissing(['pds.personalInfo', 'serviceRecordEntries' => fn ($query) => $query
            ->verified()->with(['lwopPeriods', 'evidence:id,service_record_entry_id,document_type,title,sha256'])]);

        $entries = $employee->serviceRecordEntries
            ->sortBy('service_from')
            ->map(fn (ServiceRecordEntry $entry) => [
                'id' => $entry->id,
                'from' => $entry->service_from?->toDateString(),
                'to' => $entry->service_to?->toDateString(),
                'appointment_issued_at' => $entry->appointment_issued_at?->toDateString(),
                'assumed_at' => $entry->assumed_at?->toDateString(),
                'position' => $entry->position_title,
                'employment_status' => $entry->employment_status,
                'appointment_nature' => $entry->appointment_nature,
                'salary_amount' => $entry->salary_amount,
                'salary_basis' => $entry->salary_basis,
                'salary_grade' => $entry->salary_grade,
                'salary_step' => $entry->salary_step,
                'agency' => $entry->agency_name,
                'station_place' => $entry->station_place,
                'branch_division' => $entry->branch_division,
                'plantilla_item_no' => $entry->plantilla_item_no,
                'lwop_periods' => $entry->lwopPeriods->map(fn ($period) => [
                    'from' => $period->date_from?->toDateString(),
                    'to' => $period->date_to?->toDateString(),
                    'days' => $period->days,
                ])->values()->all(),
                'separation_date' => $entry->separation_date?->toDateString(),
                'separation_cause' => $entry->separation_cause,
                'credited_as_government_service' => $entry->credited_as_government_service,
            ])->values()->all();

        $personal = $employee->pds?->personalInfo;

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_no' => $employee->employee_no,
            'birth_date' => $personal?->date_of_birth,
            'birth_place' => $personal?->place_of_birth,
            'generated_at' => now()->toIso8601String(),
            'entries' => $entries,
        ];
    }

    public function createVersion(User $employee, array $snapshot, User $actor, ?int $requestId = null, ?int $issuedId = null): ServiceRecordVersion
    {
        return DB::transaction(function () use ($employee, $snapshot, $actor, $requestId, $issuedId) {
            $version = ((int) ServiceRecordVersion::where('user_id', $employee->id)->lockForUpdate()->max('version')) + 1;

            return ServiceRecordVersion::create([
                'user_id' => $employee->id,
                'version' => $version,
                'snapshot' => $snapshot,
                'content_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'document_request_id' => $requestId,
                'issued_document_id' => $issuedId,
                'created_by' => $actor->id,
                'certified_at' => now(),
            ]);
        });
    }
}
