<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\FacilityRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Models\WorkRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneralServicesDashboardService
{
    private const ACTIVE_STATUSES = [
        'Pending',
        'Pending Division Chief Approval',
        'Division Approved',
        'GSU Approved',
        'Approved',
        'Pending FAD Approval',
        'FAD Approved',
        'OCD Approved',
        'Submitted IT Job Request',
    ];

    private const DECLINED_STATUSES = [
        'Declined',
        'FAD Declined',
        'Rejected',
        'Cancelled',
    ];

    public function payload(): array
    {
        $today = Carbon::today();

        return [
            'generatedAt'        => now()->toIso8601String(),
            'summary'            => $this->summary($today),
            'moduleBreakdown'    => $this->moduleBreakdown(),
            'statusBreakdown'    => $this->statusBreakdown(),
            'monthlyTrend'       => $this->monthlyTrend($today),
            'driverAnalytics'    => $this->driverAnalytics($today),
            'personnelAnalytics' => $this->skilledPersonnelAnalytics($today),
            'operationalQueues'  => $this->operationalQueues($today),
            'upcomingSchedule'   => $this->upcomingSchedule($today),
        ];
    }

    public function calendarEvents(?string $start = null, ?string $end = null): array
    {
        $from = $start ? Carbon::parse($start)->startOfDay() : Carbon::today()->subMonths(2)->startOfMonth();
        $to = $end ? Carbon::parse($end)->endOfDay() : Carbon::today()->addMonths(6)->endOfMonth();

        return collect()
            ->merge($this->vehicleEvents($from, $to))
            ->merge($this->facilityEvents($from, $to))
            ->merge($this->serviceEvents($from, $to))
            ->merge($this->workEvents($from, $to))
            ->sortBy('start')
            ->values()
            ->all();
    }

    private function summary(Carbon $today): array
    {
        $monthStart = $today->copy()->startOfMonth();
        $lastMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $today->copy()->subMonthNoOverflow()->endOfMonth();

        $total = VehicleRequest::count()
            + FacilityRequest::count()
            + ServiceRequest::count()
            + WorkRequest::count();

        $thisMonth = $this->createdBetween($monthStart, $today->copy()->endOfDay());
        $lastMonth = $this->createdBetween($lastMonthStart, $lastMonthEnd);

        return [
            'totalRequests'       => $total,
            'activeRequests'      => $this->countByStatuses(self::ACTIVE_STATUSES),
            'completedRequests'   => $this->completedCount(),
            'declinedRequests'    => $this->countByStatuses(self::DECLINED_STATUSES),
            'thisMonth'           => $thisMonth,
            'lastMonth'           => $lastMonth,
            'monthDelta'          => $thisMonth - $lastMonth,
            'scheduledToday'      => count($this->calendarEvents($today->toDateString(), $today->toDateString())),
            'scheduledThisWeek'   => count($this->calendarEvents($today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString())),
            'overdueWorkRequests' => WorkRequest::whereNotIn('status', ['Completed', ...self::DECLINED_STATUSES])
                ->whereNotNull('expected_completion_date')
                ->whereDate('expected_completion_date', '<', $today)
                ->count(),
            'unassignedVehicles'  => VehicleRequest::whereIn('status', ['Approved', 'FAD Approved', 'OCD Approved'])
                ->whereNull('driver_id')
                ->count(),
            'unassignedWork'      => WorkRequest::whereIn('status', ['Pending', 'Division Approved', 'GSU Approved', 'Pending FAD Approval', 'FAD Approved'])
                ->whereNull('assigned_user_id')
                ->count(),
        ];
    }

    private function moduleBreakdown(): array
    {
        return [
            $this->moduleRow('Vehicle Requests', VehicleRequest::query(), ['OCD Approved', 'Completed']),
            $this->moduleRow('Facility Requests', FacilityRequest::query(), ['Approved', 'OCD Approved']),
            $this->moduleRow('Request for Services', ServiceRequest::query(), ['FAD Approved', 'GSU Approved']),
            $this->moduleRow('Work Requests', WorkRequest::query(), ['Completed']),
        ];
    }

    private function moduleRow(string $label, $query, array $completedStatuses): array
    {
        $total = (clone $query)->count();
        $declined = (clone $query)->whereIn('status', self::DECLINED_STATUSES)->count();
        $completed = (clone $query)->whereIn('status', $completedStatuses)->count();

        return [
            'label'     => $label,
            'total'     => $total,
            'active'    => max(0, $total - $completed - $declined),
            'completed' => $completed,
            'declined'  => $declined,
        ];
    }

    private function statusBreakdown(): array
    {
        return collect([
            $this->statusRows('Vehicle', 'vehicle_requests'),
            $this->statusRows('Facility', 'facility_requests'),
            $this->statusRows('Service', 'service_requests'),
            $this->statusRows('Work', 'work_requests'),
        ])->flatten(1)->values()->all();
    }

    private function statusRows(string $module, string $table): array
    {
        return DB::table($table)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'module' => $module,
                'status' => $row->status ?: 'Unspecified',
                'total'  => (int) $row->total,
            ])
            ->all();
    }

    private function monthlyTrend(Carbon $today): array
    {
        $months = collect(range(11, 0))
            ->map(fn ($i) => $today->copy()->subMonthsNoOverflow($i)->startOfMonth());

        return [
            'labels' => $months->map(fn (Carbon $m) => $m->format('M Y'))->all(),
            'datasets' => [
                $this->monthlyDataset('Vehicle', 'vehicle_requests', $months),
                $this->monthlyDataset('Facility', 'facility_requests', $months),
                $this->monthlyDataset('Services', 'service_requests', $months),
                $this->monthlyDataset('Work', 'work_requests', $months),
            ],
        ];
    }

    private function monthlyDataset(string $label, string $table, Collection $months): array
    {
        $start = $months->first()->copy()->startOfMonth();
        $end = $months->last()->copy()->endOfMonth();

        $rows = DB::table($table)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('ym')
            ->pluck('total', 'ym');

        return [
            'label' => $label,
            'data'  => $months->map(fn (Carbon $m) => (int) ($rows[$m->format('Y-m')] ?? 0))->all(),
        ];
    }

    private function driverAnalytics(Carbon $today): array
    {
        $drivers = User::select('id', 'name', 'position')
            ->where('position', 'like', '%Driver%')
            ->where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get();

        $rows = $drivers->map(function (User $driver) use ($today) {
            $assigned = VehicleRequest::where('driver_id', $driver->id);
            $completed = (clone $assigned)->whereIn('status', ['OCD Approved', 'Completed'])->count();
            $upcoming = (clone $assigned)
                ->whereNotIn('status', self::DECLINED_STATUSES)
                ->where(function ($query) use ($today) {
                    $query->whereDate('date_needed', '>=', $today)
                        ->orWhereJsonContains('date_needed_multiple', $today->toDateString());
                })
                ->count();

            return [
                'id'          => $driver->id,
                'name'        => $driver->name,
                'position'    => $driver->position,
                'assigned'    => (clone $assigned)->count(),
                'scheduled'   => $completed,
                'active'      => (clone $assigned)->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'declined'    => (clone $assigned)->whereIn('status', self::DECLINED_STATUSES)->count(),
                'upcoming'    => $upcoming,
                'passengers'  => (int) (clone $assigned)->sum('passengers'),
                'lastTripAt'  => $this->dateString((clone $assigned)->latest('date_needed')->first()?->date_needed),
            ];
        });

        return [
            'rows' => $rows->sortByDesc('assigned')->values()->all(),
            'unassignedApproved' => VehicleRequest::whereIn('status', ['Approved', 'FAD Approved', 'OCD Approved'])
                ->whereNull('driver_id')
                ->count(),
        ];
    }

    private function skilledPersonnelAnalytics(Carbon $today): array
    {
        $personnel = User::select('id', 'name', 'position')
            ->where('position', 'like', '%Skilled%')
            ->where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get();

        $rows = $personnel->map(function (User $user) use ($today) {
            $assigned = WorkRequest::where('assigned_user_id', $user->id);
            $completedDurations = (clone $assigned)
                ->where('status', 'Completed')
                ->whereNotNull('date_completed')
                ->get(['created_at', 'date_completed'])
                ->map(fn (WorkRequest $work) => Carbon::parse($work->created_at)->diffInDays(Carbon::parse($work->date_completed)));

            return [
                'id'             => $user->id,
                'name'           => $user->name,
                'position'       => $user->position,
                'assigned'       => (clone $assigned)->count(),
                'completed'      => (clone $assigned)->where('status', 'Completed')->count(),
                'active'         => (clone $assigned)->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'overdue'        => (clone $assigned)
                    ->whereNotIn('status', ['Completed', ...self::DECLINED_STATUSES])
                    ->whereNotNull('expected_completion_date')
                    ->whereDate('expected_completion_date', '<', $today)
                    ->count(),
                'highPriority'   => (clone $assigned)->where('priority', 'High')->count(),
                'avgDaysToClose' => $completedDurations->count() ? round($completedDurations->avg(), 1) : null,
            ];
        });

        return [
            'rows' => $rows->sortByDesc('assigned')->values()->all(),
            'unassignedActive' => WorkRequest::whereIn('status', ['Pending', 'Division Approved', 'GSU Approved', 'Pending FAD Approval', 'FAD Approved'])
                ->whereNull('assigned_user_id')
                ->count(),
            'categoryBreakdown' => WorkRequest::select(DB::raw("COALESCE(NULLIF(category, ''), 'Uncategorized') as category"), DB::raw('COUNT(*) as total'))
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['category' => $row->category, 'total' => (int) $row->total])
                ->values()
                ->all(),
        ];
    }

    private function operationalQueues(Carbon $today): array
    {
        return [
            'overdueWork' => WorkRequest::with(['requester:id,name', 'assignedUser:id,name'])
                ->whereNotIn('status', ['Completed', ...self::DECLINED_STATUSES])
                ->whereNotNull('expected_completion_date')
                ->whereDate('expected_completion_date', '<', $today)
                ->orderBy('expected_completion_date')
                ->take(8)
                ->get()
                ->map(fn (WorkRequest $work) => [
                    'id'        => $work->id,
                    'title'     => $work->issue,
                    'date'      => $this->dateString($work->expected_completion_date),
                    'status'    => $work->status,
                    'requester' => $work->requester?->name,
                    'assignee'  => $work->assignedUser?->name,
                    'url'       => route('work-requests.index', ['search' => $work->id]),
                ])
                ->all(),
            'unassignedVehicles' => VehicleRequest::with('requester:id,name')
                ->whereIn('status', ['Approved', 'FAD Approved', 'OCD Approved'])
                ->whereNull('driver_id')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (VehicleRequest $vehicle) => [
                    'id'        => $vehicle->id,
                    'title'     => $vehicle->purpose,
                    'date'      => $this->dateString($vehicle->date_needed),
                    'status'    => $vehicle->status,
                    'requester' => $vehicle->requester?->name,
                    'url'       => route('vehicle-requests.index', ['search' => $vehicle->id]),
                ])
                ->all(),
            'unassignedWork' => WorkRequest::with('requester:id,name')
                ->whereIn('status', ['Pending', 'Division Approved', 'GSU Approved', 'Pending FAD Approval', 'FAD Approved'])
                ->whereNull('assigned_user_id')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (WorkRequest $work) => [
                    'id'        => $work->id,
                    'title'     => $work->issue,
                    'date'      => $this->dateString($work->expected_completion_date ?? $work->created_at),
                    'status'    => $work->status,
                    'requester' => $work->requester?->name,
                    'url'       => route('work-requests.index', ['search' => $work->id]),
                ])
                ->all(),
        ];
    }

    private function upcomingSchedule(Carbon $today): array
    {
        return collect($this->calendarEvents($today->toDateString(), $today->copy()->addDays(7)->toDateString()))
            ->take(12)
            ->values()
            ->all();
    }

    private function vehicleEvents(Carbon $from, Carbon $to): array
    {
        $vehicles = Vehicle::pluck('plate_number', 'name');

        return VehicleRequest::with(['requester:id,name', 'driver:id,name'])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('date_needed', [$from->toDateString(), $to->toDateString()])
                    ->orWhereNotNull('date_needed_multiple');
            })
            ->get()
            ->flatMap(function (VehicleRequest $request) use ($from, $to, $vehicles) {
                return collect($this->vehicleDates($request))
                    ->filter(fn ($date) => Carbon::parse($date)->betweenIncluded($from, $to))
                    ->map(fn ($date) => $this->eventRow(
                        id: "vehicle-{$request->id}-{$date}",
                        type: 'vehicle',
                        module: 'Vehicle Request',
                        title: $request->purpose ?: 'Vehicle request',
                        date: $date,
                        startTime: $request->time_of_departure,
                        endTime: $request->eta,
                        status: $request->status,
                        requester: $request->requester?->name,
                        assignee: $request->driver?->name,
                        location: trim(($request->destination ?? '') . ($request->vehicle_type ? ' · ' . $request->vehicle_type : '') . ($vehicles[$request->vehicle_type] ?? null ? ' · ' . $vehicles[$request->vehicle_type] : '')),
                        url: route('vehicle-requests.index', ['search' => $request->id]),
                        requestId: $request->id,
                    ));
            })
            ->values()
            ->all();
    }

    private function facilityEvents(Carbon $from, Carbon $to): array
    {
        $facilities = Facility::pluck('name', 'id');

        return FacilityRequest::with('requester:id,name')
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('date_start', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('date_end', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereDate('date_start', '<=', $from->toDateString())
                            ->whereDate('date_end', '>=', $to->toDateString());
                    });
            })
            ->get()
            ->flatMap(function (FacilityRequest $request) use ($from, $to, $facilities) {
                if (! $request->date_start) {
                    return [];
                }

                $start = Carbon::parse($request->date_start)->max($from);
                $end = Carbon::parse($request->date_end ?: $request->date_start)->min($to);
                $venueNames = collect($request->venue ?: [])
                    ->when(! is_array($request->venue), fn ($items) => collect([$request->venue]))
                    ->map(fn ($id) => $facilities[$id] ?? $id)
                    ->filter()
                    ->implode(', ');

                return collect(CarbonPeriod::create($start, $end))
                    ->map(fn (Carbon $date) => $this->eventRow(
                        id: "facility-{$request->id}-{$date->toDateString()}",
                        type: 'facility',
                        module: 'Facility Request',
                        title: $request->activity ?: ($request->purpose ?: 'Facility request'),
                        date: $date->toDateString(),
                        startTime: $request->time_start,
                        endTime: $request->time_end,
                        status: $request->status,
                        requester: $request->requester?->name ?? $request->requestor,
                        assignee: null,
                        location: $venueNames,
                        url: route('facility-requests.index', ['search' => $request->id]),
                        requestId: $request->id,
                    ));
            })
            ->values()
            ->all();
    }

    private function serviceEvents(Carbon $from, Carbon $to): array
    {
        return ServiceRequest::with('requester:id,name')
            ->whereBetween('date_needed', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(fn (ServiceRequest $request) => $this->eventRow(
                id: "service-{$request->id}",
                type: 'service',
                module: 'Request for Services',
                title: $request->service_type ?: 'Request for services',
                date: $this->dateString($request->date_needed),
                startTime: $request->time_needed,
                endTime: null,
                status: $request->status,
                requester: $request->requester?->name,
                assignee: null,
                location: $request->purposes,
                url: route('service-requests.index', ['search' => $request->id]),
                requestId: $request->id,
            ))
            ->values()
            ->all();
    }

    private function workEvents(Carbon $from, Carbon $to): array
    {
        return WorkRequest::with(['requester:id,name', 'assignedUser:id,name', 'division:id,name', 'office:id,name'])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('expected_completion_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('date_completed', [$from->toDateString(), $to->toDateString()]);
            })
            ->get()
            ->flatMap(function (WorkRequest $request) use ($from, $to) {
                $events = [];
                if ($request->expected_completion_date && Carbon::parse($request->expected_completion_date)->betweenIncluded($from, $to)) {
                    $events[] = $this->eventRow(
                        id: "work-due-{$request->id}",
                        type: 'work',
                        module: 'Work Request',
                        title: $request->issue ?: 'Work request',
                        date: $this->dateString($request->expected_completion_date),
                        startTime: null,
                        endTime: null,
                        status: $request->status,
                        requester: $request->requester?->name,
                        assignee: $request->assignedUser?->name,
                        location: collect([$request->division?->name, $request->office?->name])->filter()->implode(' · '),
                        url: route('work-requests.index', ['search' => $request->id]),
                        requestId: $request->id,
                    );
                }

                if ($request->date_completed && Carbon::parse($request->date_completed)->betweenIncluded($from, $to)) {
                    $events[] = $this->eventRow(
                        id: "work-completed-{$request->id}",
                        type: 'work-completed',
                        module: 'Completed Work',
                        title: $request->issue ?: 'Completed work',
                        date: $this->dateString($request->date_completed),
                        startTime: null,
                        endTime: null,
                        status: 'Completed',
                        requester: $request->requester?->name,
                        assignee: $request->assignedUser?->name,
                        location: collect([$request->division?->name, $request->office?->name])->filter()->implode(' · '),
                        url: route('work-requests.index', ['search' => $request->id]),
                        requestId: $request->id,
                    );
                }

                return $events;
            })
            ->values()
            ->all();
    }

    private function eventRow(
        string $id,
        string $type,
        string $module,
        string $title,
        string $date,
        mixed $startTime,
        mixed $endTime,
        ?string $status,
        ?string $requester,
        ?string $assignee,
        ?string $location,
        string $url,
        int $requestId,
    ): array {
        $start = $this->combineDateTime($date, $startTime);
        $end = $endTime ? $this->combineDateTime($date, $endTime) : null;

        return [
            'id'              => $id,
            'title'           => $title,
            'start'           => $start,
            'end'             => $end,
            'allDay'          => ! $startTime,
            'backgroundColor' => $this->eventColor($type),
            'borderColor'     => $this->eventColor($type),
            'extendedProps'   => [
                'requestId' => $requestId,
                'type'      => $type,
                'module'    => $module,
                'status'    => $status ?: 'Unspecified',
                'requester' => $requester,
                'assignee'  => $assignee,
                'location'  => $location,
                'url'       => $url,
            ],
        ];
    }

    private function eventColor(string $type): string
    {
        return match ($type) {
            'vehicle'        => '#2563eb',
            'facility'       => '#059669',
            'service'        => '#7c3aed',
            'work'           => '#d97706',
            'work-completed' => '#0f766e',
            default          => '#64748b',
        };
    }

    private function vehicleDates(VehicleRequest $request): array
    {
        if (is_array($request->date_needed_multiple) && count($request->date_needed_multiple) > 0) {
            return $request->date_needed_multiple;
        }

        return $request->date_needed ? [$this->dateString($request->date_needed)] : [];
    }

    private function countByStatuses(array $statuses): int
    {
        return VehicleRequest::whereIn('status', $statuses)->count()
            + FacilityRequest::whereIn('status', $statuses)->count()
            + ServiceRequest::whereIn('status', $statuses)->count()
            + WorkRequest::whereIn('status', $statuses)->count();
    }

    private function completedCount(): int
    {
        return VehicleRequest::whereIn('status', ['OCD Approved', 'Completed'])->count()
            + FacilityRequest::whereIn('status', ['Approved', 'OCD Approved'])->count()
            + ServiceRequest::whereIn('status', ['FAD Approved', 'GSU Approved'])->count()
            + WorkRequest::where('status', 'Completed')->count();
    }

    private function createdBetween(Carbon $start, Carbon $end): int
    {
        return VehicleRequest::whereBetween('created_at', [$start, $end])->count()
            + FacilityRequest::whereBetween('created_at', [$start, $end])->count()
            + ServiceRequest::whereBetween('created_at', [$start, $end])->count()
            + WorkRequest::whereBetween('created_at', [$start, $end])->count();
    }

    private function combineDateTime(string $date, mixed $time): string
    {
        if (! $time) {
            return Carbon::parse($date)->toDateString();
        }

        return Carbon::parse($date . ' ' . substr((string) $time, 0, 5))->format('Y-m-d\TH:i:s');
    }

    private function dateString(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }
}
