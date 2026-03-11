<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Consultation;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\MessengerialRequest;
use App\Models\FacilityRequest;
use App\Models\ServiceRequest;
use App\Models\WorkRequest;
use App\Models\Borrowing;
use App\Models\DocumentRouting;
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
        $userRoles = $authUser ? $authUser->getRolesCollection() : collect();

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
                    ]
                    : null,
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
            // Number of consultations that are pending or active (for sidebar badge)
            'consultationsNotificationCount' => fn () => Consultation::whereIn('status', ['Pending', 'Active'])->count(),
            // Number of IT job requests with status containing 'Pending'
            'itJobRequestsNotificationCount' => fn () => ITJobRequest::where('status', 'like', '%Pending%')->count(),
            // Number of vehicle requests with status = 'Pending'
            'vehicleRequestsNotificationCount' => function () use ($request) {
                $user = $request->user();
                // DivisionChief: pending requests in their division
                if ($user && $user->hasRole('DivisionChief')) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                    return VehicleRequest::where('status', 'Pending')->whereIn('requestor_id', $userIds)->count();
                }
                // GSU Head: show count of approved vehicle requests
                if ($user && $user->hasRole('GSU Head')) {
                    return VehicleRequest::where('status', 'Approved')->count();
                }
                // default: pending requests
                return VehicleRequest::where('status', 'Pending')->count();
            },
            // Number of messengerial requests with status = 'Pending'
            'messengerialRequestsNotificationCount' => fn () => MessengerialRequest::where('status', 'Pending')->count(),
            // Number of facility requests with status = 'Pending'
            'facilityRequestsNotificationCount' => function () use ($request) {
                $user = $request->user();
                if ($user && $user->hasRole('DivisionChief')) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id')->toArray();
                    $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id')->toArray();
                    return FacilityRequest::where('status', 'Pending')
                        ->where(function($q) use ($userIds, $divisionIds) {
                            $q->whereIn('requestor_id', $userIds)
                              ->orWhereHas('requester', function($q2) use ($divisionIds) {
                                  $q2->whereIn('division_id', $divisionIds);
                              });
                        })->count();
                }
                return FacilityRequest::where('status', 'Pending')->count();
            },
            // Number of service requests with status = 'Pending'
            'serviceRequestsNotificationCount' => function () use ($request) {
                $user = $request->user();
                if ($user && $user->hasRole('DivisionChief')) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                    return ServiceRequest::where('status', 'Pending')->whereIn('requestor_id', $userIds)->count();
                }
                return ServiceRequest::where('status', 'Pending')->count();
            },
            // Number of gatepasses pending. For DivisionChiefs show only gatepasses
            // requested by employees in their division(s).
            'gatepassNotificationCount' => function () use ($request) {
                $user = $request->user();
                $db = \Illuminate\Support\Facades\DB::table('gatepass');
                if ($user && ($user->role->name ?? '') === 'DivisionChief') {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $badgeNumbers = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('badge_id')->filter()->toArray();
                    if (empty($badgeNumbers)) return 0;
                    return $db->where('status', 'Pending')->whereIn('badgeNumber', $badgeNumbers)->count();
                }
                return $db->where('status', 'Pending')->count();
            },
            // Number of work requests for the sidebar badge
            'workRequestsNotificationCount' => function () use ($request) {
                $user = $request->user();
                // For DivisionChief, show requests waiting for their DivisionChief approval step (Pending FAD Approval)
                if ($user && $user->hasRole('DivisionChief')) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $userIds = \App\Models\User::whereIn('division_id', $divisionIds)->pluck('id');
                    return WorkRequest::where('status', 'Pending FAD Approval')->whereIn('requester_id', $userIds)->count();
                }
                // Default behavior: count generic Pending work requests
                return WorkRequest::where('status', 'Pending')->count();
            },
            // Number of borrowings that are overdue (due_date before today and not yet returned)
            'borrowingsOverdueCount' => fn () => Borrowing::whereNull('return_date')->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today())->count(),
            // Number of document routing steps pending the current user's action
            'documentTrackingNotificationCount' => function () use ($request) {
                $user = $request->user();
                if (!$user) return 0;
                return DocumentRouting::where('receiver_id', $user->id)
                    ->whereIn('status', ['Pending', 'Received'])
                    ->count();
            },
        ]);
    }

}
