<?php

namespace App\Services;

use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityEvaluation;
use App\Models\DocumentRouting;
use App\Models\FacilityRequest;
use App\Models\HR\LeaveApplication;
use App\Models\Issuance;
use App\Models\ITJobRequest;
use App\Models\OedIssuance;
use App\Models\ServiceRequest;
use App\Models\TravelRequest;
use App\Models\User;
use App\Models\VehicleRequest;
use App\Models\WorkRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PersonalDashboardService
{
    public function __construct(
        private readonly PersonNameFormatter $names,
    ) {}

    public function payload(User $user): array
    {
        $approvalTabs = $this->rescue('approval inbox', fn () => (new ApprovalInboxService($user))->getPendingItems(), []);
        $documents = $this->documentsForAction($user);
        $acknowledgments = $this->acknowledgments($user);
        $activityEvaluations = $this->activityEvaluations($user);
        $requests = $this->myRequests($user);
        $notifications = $this->notifications($user);
        $calendarEvents = $this->calendarEvents($user);

        $approvalCount = collect($approvalTabs)->sum('count');
        $documentActionCount = count($documents);
        $acknowledgmentCount = count($acknowledgments);
        $activityEvaluationCount = count($activityEvaluations);
        $needsActionCount = $approvalCount + $documentActionCount + $acknowledgmentCount + $activityEvaluationCount;
        $overdueCount = collect($documents)->where('is_overdue', true)->count();

        return [
            'profile' => $this->profile($user),
            'summary' => [
                'needs_action' => $needsActionCount,
                'approval_items' => $approvalCount,
                'documents_due' => $documentActionCount,
                'overdue_documents' => $overdueCount,
                'unread_notifications' => (int) $user->unreadNotifications()->count(),
                'active_requests' => count($requests),
                'upcoming_events' => collect($calendarEvents)
                    ->filter(fn ($event) => Carbon::parse($event['start'])->gte(now()->startOfDay()))
                    ->count(),
            ],
            'approvalTabs' => $approvalTabs,
            'documentsForAction' => $documents,
            'acknowledgments' => $acknowledgments,
            'activityEvaluations' => $activityEvaluations,
            'myRequests' => $requests,
            'notifications' => $notifications,
            'calendarEvents' => $calendarEvents,
            'quickLinks' => $this->rescue('quick links', fn () => $this->quickLinks($user, $approvalCount), []),
            'announcements' => $this->rescue('announcements', fn () => $this->announcements($user), []),
            'canOnlinePunch' => $user->hasPermission('hr.online-punch.record'),
        ];
    }

    /** Latest published announcements addressed to this user. */
    private function announcements(User $user): array
    {
        return \App\Models\Administration\Announcement::visibleTo($user)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'poster_path', 'published_at'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'has_poster' => (bool) $a->poster_path,
                'published_at' => $a->published_at?->toIso8601String(),
            ])
            ->all();
    }

    private function profile(User $user): array
    {
        $divisionName = null;
        if ($user->division_id) {
            $divisionName = DB::table('divisions')
                ->selectRaw("COALESCE(NULLIF(TRIM(acronym), ''), division_name) as display_name")
                ->where('id', $user->division_id)
                ->value('display_name');
        }

        $officeName = null;
        if ($user->office_id) {
            $officeName = DB::table('offices')->where('id', $user->office_id)->value('name');
        }

        return [
            'name' => $user->name,
            'nickname' => $user->nickname,
            'first_name' => $this->names->givenName($user),
            'position' => $user->position,
            'division' => $divisionName,
            'office' => $officeName,
            'roles' => $user->roles()->pluck('name')->values()->all(),
        ];
    }

    private function documentsForAction(User $user): array
    {
        return $this->rescue('dashboard documents', function () use ($user) {
            return DocumentRouting::with('document:id,tracking_no,subject,urgency,deadline_at,overall_status')
                ->where('receiver_id', $user->id)
                ->whereIn('status', ['Pending', 'Received'])
                ->latest('due_at')
                ->limit(8)
                ->get()
                ->map(fn (DocumentRouting $routing) => [
                    'id' => $routing->id,
                    'document_id' => $routing->document_id,
                    'title' => $routing->document?->subject ?? 'Untitled document',
                    'reference' => $routing->document?->tracking_no ?? "#{$routing->document_id}",
                    'status' => $routing->status,
                    'due_at' => $routing->due_at?->toIso8601String(),
                    'is_overdue' => $routing->is_overdue,
                    'urgency' => $routing->document?->urgency,
                    'url' => route('document-tracking.show', $routing->document_id),
                ])
                ->values()
                ->all();
        }, []);
    }

    private function acknowledgments(User $user): array
    {
        return array_merge(
            $this->issuanceAcknowledgments($user),
            $this->knowledgeAcknowledgments($user)
        );
    }

    private function issuanceAcknowledgments(User $user): array
    {
        return $this->rescue('dashboard issuance acknowledgments', function () use ($user) {
            return Issuance::query()
                ->select('issuances.id', 'issuances.type', 'issuances.control_number', 'issuances.title', 'issuances.released_at')
                ->join('issuance_recipients', 'issuance_recipients.issuance_id', '=', 'issuances.id')
                ->where('issuance_recipients.user_id', $user->id)
                ->whereNull('issuance_recipients.acknowledged_at')
                ->where('issuances.status', 'released')
                ->latest('issuances.released_at')
                ->limit(6)
                ->get()
                ->map(fn (Issuance $issuance) => [
                    'id' => 'issuance-'.$issuance->id,
                    'title' => $issuance->title,
                    'reference' => $issuance->control_number ?? strtoupper((string) $issuance->type),
                    'kind' => 'Official Issuance',
                    'date' => $issuance->released_at?->toIso8601String(),
                    'url' => route('issuances.show', $issuance->id),
                ])
                ->values()
                ->all();
        }, []);
    }

    private function knowledgeAcknowledgments(User $user): array
    {
        return $this->rescue('dashboard knowledge acknowledgments', function () use ($user) {
            return OedIssuance::query()
                ->select('oed_issuances.id', 'oed_issuances.reference_no', 'oed_issuances.title', 'oed_issuances.issued_date')
                ->where('oed_issuances.status', 'active')
                ->where(function ($query) use ($user) {
                    $query->where('oed_issuances.recipient_type', 'all')
                        ->orWhereExists(function ($sub) use ($user) {
                            $sub->selectRaw('1')
                                ->from('oed_issuance_recipients')
                                ->whereColumn('oed_issuance_recipients.oed_issuance_id', 'oed_issuances.id')
                                ->where('oed_issuance_recipients.user_id', $user->id);
                        });
                })
                ->whereNotExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('oed_issuance_acknowledgments')
                        ->whereColumn('oed_issuance_acknowledgments.oed_issuance_id', 'oed_issuances.id')
                        ->where('oed_issuance_acknowledgments.user_id', $user->id);
                })
                ->latest('oed_issuances.issued_date')
                ->limit(6)
                ->get()
                ->map(fn (OedIssuance $issuance) => [
                    'id' => 'km-'.$issuance->id,
                    'title' => $issuance->title,
                    'reference' => $issuance->reference_no ?? "#{$issuance->id}",
                    'kind' => 'Knowledge Management',
                    'date' => $issuance->issued_date?->toIso8601String(),
                    'url' => route('km.show', $issuance->id),
                ])
                ->values()
                ->all();
        }, []);
    }

    private function activityEvaluations(User $user): array
    {
        return $this->rescue('dashboard activity evaluations', function () use ($user) {
            $evaluatedIds = ActivityEvaluation::where('participant_type', 'employee')
                ->where('participant_id', $user->id)
                ->pluck('activity_id')
                ->all();

            return ActivityParticipant::with('activity:id,title,start_date,end_date,venue')
                ->where('participant_id', $user->id)
                ->where('participant_type', 'employee')
                ->where('attended', 'yes')
                ->whereNotIn('activity_id', $evaluatedIds)
                ->latest()
                ->limit(6)
                ->get()
                ->filter(fn (ActivityParticipant $participant) => $participant->activity !== null)
                ->map(function (ActivityParticipant $participant) use ($user) {
                    $activity = $participant->activity;
                    $hash = md5($user->id.'-'.$activity->id);

                    return [
                        'id' => $participant->id,
                        'title' => $activity->title,
                        'reference' => 'Activity evaluation',
                        'date' => $activity->end_date?->toIso8601String() ?? $activity->start_date?->toIso8601String(),
                        'url' => route('ams.activities.evaluate.show', [$activity->id, $hash]),
                    ];
                })
                ->values()
                ->all();
        }, []);
    }

    private function myRequests(User $user): array
    {
        $requests = collect();

        $this->append($requests, $this->itRequests($user));
        $this->append($requests, $this->vehicleRequests($user));
        $this->append($requests, $this->facilityRequests($user));
        $this->append($requests, $this->serviceRequests($user));
        $this->append($requests, $this->workRequests($user));
        $this->append($requests, $this->leaveRequests($user));
        $this->append($requests, $this->travelRequests($user));

        return $requests
            ->sortByDesc('sort_at')
            ->take(12)
            ->map(fn ($item) => collect($item)->except('sort_at')->all())
            ->values()
            ->all();
    }

    private function itRequests(User $user): array
    {
        return $this->rescue('dashboard IT requests', fn () => ITJobRequest::where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ITJobRequest $request) => $this->requestRow(
                'IT Job Request',
                $request->title ?? $request->category ?? 'IT request',
                $request->itjr_no ?? "#{$request->id}",
                $request->status,
                $request->updated_at,
                route('jobrequests.index')
            ))
            ->all(), []);
    }

    private function vehicleRequests(User $user): array
    {
        return $this->rescue('dashboard vehicle requests', fn () => VehicleRequest::where('requestor_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (VehicleRequest $request) => $this->requestRow(
                'Vehicle Request',
                $request->destination ?? $request->purpose ?? 'Vehicle request',
                "#{$request->id}",
                $request->status,
                $request->updated_at,
                route('vehicle-requests.index')
            ))
            ->all(), []);
    }

    private function facilityRequests(User $user): array
    {
        return $this->rescue('dashboard facility requests', fn () => FacilityRequest::where('requestor_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (FacilityRequest $request) => $this->requestRow(
                'Facility Request',
                $request->activity ?? $request->purpose ?? 'Facility request',
                $request->reference_no ?? "#{$request->id}",
                $request->status,
                $request->updated_at,
                route('facility-requests.index')
            ))
            ->all(), []);
    }

    private function serviceRequests(User $user): array
    {
        return $this->rescue('dashboard service requests', fn () => ServiceRequest::where('requestor_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ServiceRequest $request) => $this->requestRow(
                'Service Request',
                $request->service_type ?? 'Service request',
                "#{$request->id}",
                $request->status,
                $request->updated_at,
                route('service-requests.index')
            ))
            ->all(), []);
    }

    private function workRequests(User $user): array
    {
        return $this->rescue('dashboard work requests', fn () => WorkRequest::where('requester_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (WorkRequest $request) => $this->requestRow(
                'Work Request',
                $request->issue ?? $request->category ?? 'Work request',
                "#{$request->id}",
                $request->status,
                $request->updated_at,
                route('work-requests.index')
            ))
            ->all(), []);
    }

    private function leaveRequests(User $user): array
    {
        return $this->rescue('dashboard leave requests', fn () => LeaveApplication::with('leaveType:id,name')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (LeaveApplication $request) => $this->requestRow(
                'Leave Application',
                $request->leaveType?->name ?? 'Leave application',
                $request->control_no ?? "#{$request->id}",
                $request->status,
                $request->updated_at,
                route('hr.leave.show', $request->id)
            ))
            ->all(), []);
    }

    private function travelRequests(User $user): array
    {
        return $this->rescue('dashboard travel requests', fn () => TravelRequest::where(function ($query) use ($user) {
                $query->where('traveler_id', $user->id)->orWhere('created_by', $user->id);
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (TravelRequest $request) => $this->requestRow(
                'Travel Request',
                $request->destination ?? $request->purpose ?? 'Travel request',
                $request->control_no ?? "#{$request->id}",
                $request->statusLabel(),
                $request->updated_at,
                route('travel.show', $request->id)
            ))
            ->all(), []);
    }

    private function requestRow(string $module, string $title, string $reference, ?string $status, ?Carbon $updatedAt, string $url): array
    {
        return [
            'module' => $module,
            'title' => $title,
            'reference' => $reference,
            'status' => $status ?? 'Pending',
            'updated_at' => $updatedAt?->toIso8601String(),
            'sort_at' => $updatedAt?->timestamp ?? 0,
            'url' => $url,
        ];
    }

    private function notifications(User $user): array
    {
        return $user->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'request_type' => $notification->data['request_type'] ?? 'Notification',
                'reference_no' => $notification->data['reference_no'] ?? '',
                'status' => $notification->data['status'] ?? '',
                'remarks' => $notification->data['remarks'] ?? null,
                'url' => $notification->data['url'] ?? '#',
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function calendarEvents(User $user): array
    {
        $events = collect();
        $start = now()->subDays(7)->startOfDay();
        $end = now()->addDays(75)->endOfDay();

        $this->append($events, $this->leaveEvents($user, $start, $end));
        $this->append($events, $this->travelEvents($user, $start, $end));
        $this->append($events, $this->activityEvents($user, $start, $end));
        $this->append($events, $this->facilityEvents($user, $start, $end));
        $this->append($events, $this->vehicleEvents($user, $start, $end));
        $this->append($events, $this->serviceEvents($user, $start, $end));
        $this->append($events, $this->documentDueEvents($user, $start, $end));

        return $events
            ->sortBy('start')
            ->take(80)
            ->values()
            ->all();
    }

    private function leaveEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard leave events', fn () => LeaveApplication::with('leaveType:id,name')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'hr_verified', 'forwarded', 'approved'])
            ->whereDate('date_to', '>=', $start->toDateString())
            ->whereDate('date_from', '<=', $end->toDateString())
            ->get()
            ->map(fn (LeaveApplication $leave) => $this->event(
                'leave-'.$leave->id,
                $leave->leaveType?->name ?? 'Leave',
                $leave->date_from,
                $leave->date_to?->copy()->addDay(),
                'leave',
                route('hr.leave.show', $leave->id)
            ))
            ->all(), []);
    }

    private function travelEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard travel events', fn () => TravelRequest::where(function ($query) use ($user) {
                $query->where('traveler_id', $user->id)->orWhere('created_by', $user->id);
            })
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->whereDate('end_date', '>=', $start->toDateString())
            ->whereDate('start_date', '<=', $end->toDateString())
            ->get()
            ->map(fn (TravelRequest $travel) => $this->event(
                'travel-'.$travel->id,
                $travel->destination ?? 'Official travel',
                $travel->start_date,
                $travel->end_date?->copy()->addDay(),
                'travel',
                route('travel.show', $travel->id)
            ))
            ->all(), []);
    }

    private function activityEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard activity events', fn () => ActivityParticipant::with('activity:id,title,start_date,end_date')
            ->where('participant_id', $user->id)
            ->where('participant_type', 'employee')
            ->whereHas('activity', fn ($query) => $query->whereNotNull('start_date'))
            ->get()
            ->filter(fn (ActivityParticipant $participant) => $participant->activity
                && Carbon::parse($participant->activity->end_date ?? $participant->activity->start_date)->gte($start)
                && Carbon::parse($participant->activity->start_date)->lte($end))
            ->map(fn (ActivityParticipant $participant) => $this->event(
                'activity-'.$participant->activity->id,
                $participant->activity->title,
                $participant->activity->start_date,
                $participant->activity->end_date?->copy()->addDay(),
                'activity',
                route('ams.my-activities.show', $participant->activity->id)
            ))
            ->all(), []);
    }

    private function facilityEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard facility events', fn () => FacilityRequest::where('requestor_id', $user->id)
            ->whereDate('date_end', '>=', $start->toDateString())
            ->whereDate('date_start', '<=', $end->toDateString())
            ->get()
            ->map(fn (FacilityRequest $request) => $this->event(
                'facility-'.$request->id,
                $request->activity ?? 'Facility request',
                $request->date_start,
                Carbon::parse($request->date_end ?? $request->date_start)->addDay(),
                'facility',
                route('facility-requests.index')
            ))
            ->all(), []);
    }

    private function vehicleEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard vehicle events', function () use ($user, $start, $end) {
            return VehicleRequest::where('requestor_id', $user->id)
                ->whereDate('date_needed', '>=', $start->toDateString())
                ->whereDate('date_needed', '<=', $end->toDateString())
                ->get()
                ->map(fn (VehicleRequest $request) => $this->event(
                    'vehicle-'.$request->id,
                    $request->destination ?? 'Vehicle request',
                    $request->date_needed,
                    null,
                    'vehicle',
                    route('vehicle-requests.index')
                ))
                ->all();
        }, []);
    }

    private function serviceEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard service events', fn () => ServiceRequest::where('requestor_id', $user->id)
            ->whereDate('date_needed', '>=', $start->toDateString())
            ->whereDate('date_needed', '<=', $end->toDateString())
            ->get()
            ->map(fn (ServiceRequest $request) => $this->event(
                'service-'.$request->id,
                $request->service_type ?? 'Service request',
                $request->date_needed,
                null,
                'service',
                route('service-requests.index')
            ))
            ->all(), []);
    }

    private function documentDueEvents(User $user, Carbon $start, Carbon $end): array
    {
        return $this->rescue('dashboard document due events', fn () => DocumentRouting::with('document:id,tracking_no,subject')
            ->where('receiver_id', $user->id)
            ->whereIn('status', ['Pending', 'Received'])
            ->whereBetween('due_at', [$start, $end])
            ->get()
            ->map(fn (DocumentRouting $routing) => $this->event(
                'document-'.$routing->id,
                'Due: '.($routing->document?->subject ?? $routing->document?->tracking_no ?? 'Document'),
                $routing->due_at,
                null,
                $routing->is_overdue ? 'overdue' : 'document',
                route('document-tracking.show', $routing->document_id)
            ))
            ->all(), []);
    }

    private function event(string $id, string $title, mixed $start, mixed $end, string $type, string $url): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'start' => $start instanceof Carbon ? $start->toDateString() : Carbon::parse($start)->toDateString(),
            'end' => $end ? ($end instanceof Carbon ? $end->toDateString() : Carbon::parse($end)->toDateString()) : null,
            'type' => $type,
            'url' => $url,
            'className' => 'dash-calendar-'.$type,
        ];
    }

    private function quickLinks(User $user, int $approvalCount): array
    {
        $links = [
            ['label' => 'My Leave', 'description' => 'Leave applications and credits', 'url' => route('hr.leave.index'), 'tone' => 'indigo'],
            ['label' => 'My Requests', 'description' => 'IT and service request status', 'url' => route('jobrequests.index'), 'tone' => 'indigo'],
            ['label' => 'Document Tracking', 'description' => 'Routed documents and files', 'url' => route('document-tracking.index'), 'tone' => 'indigo'],
            ['label' => 'My Activities', 'description' => 'Activities, evaluations, certificates', 'url' => route('ams.my-activities.index'), 'tone' => 'indigo'],
        ];

        if ($approvalCount > 0 || $this->isApprover($user)) {
            array_unshift($links, [
                'label' => 'Approval Inbox',
                'description' => $approvalCount > 0 ? "{$approvalCount} item(s) need action" : 'Review routed approvals',
                'url' => route('approvals.inbox'),
                'tone' => 'indigo',
            ]);
        }

        if ($user->hasPermission('ipcr.view')) {
            $links[] = ['label' => 'IPCR / PMS', 'description' => 'Performance targets and ratings', 'url' => route('employee-ipcr.index'), 'tone' => 'indigo'];
        }

        return $links;
    }

    private function isApprover(User $user): bool
    {
        return $user->hasAnyRole(['Administrator', 'DivisionChief', 'GSU Head', 'OCD', 'FAD Chief'])
            || str_contains($user->position ?? '', 'FAD')
            || $user->hasPermission('hr.leave.approve');
    }

    private function append(Collection $target, array $items): void
    {
        foreach ($items as $item) {
            $target->push($item);
        }
    }

    private function rescue(string $label, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            logger()->warning("Personal dashboard {$label} error: ".$e->getMessage());
            return $fallback;
        }
    }
}
