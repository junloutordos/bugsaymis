<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use App\Models\Consultation;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\MessengerialRequest;
use App\Models\FacilityRequest;
use App\Models\ServiceRequest;
use App\Models\WorkRequest;
use App\Models\Borrowing;
use App\Models\Committee;
use App\Models\DocumentRouting;
use App\Models\SpecialAssignment;
use Carbon\Carbon;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    // app/Http/Middleware/HandleInertiaRequests.php
    public function share(Request $request): array
    {
        $authUser = $request->user();
        if ($authUser) {
            $authUser->loadMissing('roles');
        }
        $userRoles = $authUser ? $authUser->roles : collect();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $authUser
                    ? [
                        'id' => $authUser->id,
                        'name' => $authUser->name,
                        'role' => $userRoles->first(),                         // backward compat: primary role object
                        'roles' => $userRoles->values(),                       // all role objects [{id, name}]
                        'roleNames' => $userRoles->pluck('name')->toArray(),   // ['Staff', 'DivisionChief']
                        'position' => $authUser->position,
                        'email' => $authUser->email,
                        // expose numeric/string role ids for client-side checks (may be CSV)
                        'role_id' => $authUser->role_id ?? $userRoles->pluck('id')->implode(','),
                        'profile_picture' => $authUser->profile_picture,
                        'electronic_signature' => $authUser->electronic_signature,
                        'permissions' => $authUser->getPermissions(),
                    ]
                    : null,
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
            // ── Sidebar badge counts — cached 60s to reduce DB queries ────────
            'consultationsNotificationCount' => function () {
                try {
                    return Cache::remember('badge.consultations', 60, fn () =>
                        Consultation::whereIn('status', ['Pending', 'Active'])->count()
                    );
                } catch (\Throwable $e) { return 0; }
            },
            'itJobRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.it_requests.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasRole('DivisionChief')) {
                            $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                            if ($divisionIds->isNotEmpty()) {
                                $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                                return ITJobRequest::where('status', 'Pending Division Chief Approval')
                                    ->whereIn('user_id', $userIds)->count();
                            }
                        }
                        if ($user->hasRole('OCD')) {
                            return ITJobRequest::where('status', 'Pending OCD Approval')->count();
                        }
                        return ITJobRequest::whereIn('status', ['In Progress'])->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'vehicleRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.vehicles.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasRole('DivisionChief')) {
                            $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                            if ($divisionIds->isNotEmpty()) {
                                $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                                return VehicleRequest::where('status', 'Pending')->whereIn('requestor_id', $userIds)->count();
                            }
                        }
                        return VehicleRequest::where('status', 'Pending')->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'messengerialRequestsNotificationCount' => function () {
                try {
                    return Cache::remember('badge.messengerial', 60, fn () =>
                        MessengerialRequest::where('status', 'Pending')->count()
                    );
                } catch (\Throwable $e) { return 0; }
            },
            'facilityRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.facility.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasRole('DivisionChief')) {
                            $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id')->toArray();
                            if (!empty($divisionIds)) {
                                $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id')->toArray();
                                return FacilityRequest::where('status', 'Pending')
                                    ->where(function ($q) use ($userIds, $divisionIds) {
                                        $q->whereIn('requestor_id', $userIds)
                                          ->orWhereHas('requester', fn ($q2) => $q2->whereIn('division_id', $divisionIds));
                                    })->count();
                            }
                        }
                        return FacilityRequest::where('status', 'Pending')->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'serviceRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.service.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasRole('DivisionChief')) {
                            $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                            if ($divisionIds->isNotEmpty()) {
                                $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                                return ServiceRequest::where('status', 'Pending')->whereIn('requestor_id', $userIds)->count();
                            }
                        }
                        return ServiceRequest::where('status', 'Pending')->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'gatepassNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.gatepass.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        $db = \Illuminate\Support\Facades\DB::table('gatepass');
                        if ($user->hasRole('DivisionChief')) {
                            $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                            if ($divisionIds->isNotEmpty()) {
                                $badgeNumbers = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('badge_id')->filter()->toArray();
                                if (!empty($badgeNumbers)) {
                                    return $db->where('status', 'Pending')->whereIn('badgeNumber', $badgeNumbers)->count();
                                }
                            }
                        }
                        return $db->where('status', 'Pending')->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'workRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.work.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasRole('DivisionChief')) {
                            $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                            if ($divisionIds->isNotEmpty()) {
                                $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                                return WorkRequest::where('status', 'Pending FAD Approval')->whereIn('requester_id', $userIds)->count();
                            }
                        }
                        return WorkRequest::where('status', 'Pending')->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'borrowingsOverdueCount' => function () {
                try {
                    return Cache::remember('badge.borrowings_overdue', 300, fn () =>
                        Borrowing::whereNull('return_date')->whereNotNull('due_date')
                            ->whereDate('due_date', '<', Carbon::today())->count()
                    );
                } catch (\Throwable $e) { return 0; }
            },
            'documentTrackingNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    return Cache::remember('badge.documents.u' . $user->id, 60, fn () =>
                        DocumentRouting::where('receiver_id', $user->id)
                            ->whereIn('status', ['Pending', 'Received'])->count()
                    );
                } catch (\Throwable $e) { return 0; }
            },
            'isPMRater' => function () use ($request) {
                $user = $request->user();
                if (!$user) return false;
                return Cache::remember('badge.pm_rater.u' . $user->id, 300, fn () =>
                    Committee::where('head_id', $user->id)->exists()
                        || SpecialAssignment::where('coordinator_id', $user->id)->exists()
                );
            },
            'appVersion' => function () {
                try {
                    return Cache::remember('app.version', 3600, function () {
                        $versions = \App\Models\AppVersion::orderBy('date', 'desc')->get();
                        $current  = $versions->firstWhere('is_current', true) ?? $versions->first();
                        return [
                            'current' => $current?->version ?? '1.0.0',
                            'history' => $versions->map(fn ($v) => [
                                'version' => $v->version,
                                'date'    => $v->date->format('Y-m-d'),
                                'remarks' => $v->remarks,
                            ])->values()->toArray(),
                        ];
                    });
                } catch (\Throwable $e) {
                    return ['current' => '1.0.0', 'history' => []];
                }
            },
        ]);
    }

}
