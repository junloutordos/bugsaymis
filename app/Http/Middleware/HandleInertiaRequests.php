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
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'role' => $request->user()->role,
                        'position' => $request->user()->position, // ✅ ADD THIS
                        'email' => $request->user()->email,
                        'profile_picture' => $request->user()->profile_picture,
                        'electronic_signature' => $request->user()->electronic_signature,
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
            'vehicleRequestsNotificationCount' => fn () => VehicleRequest::where('status', 'Pending')->count(),
            // Number of messengerial requests with status = 'Pending'
            'messengerialRequestsNotificationCount' => fn () => MessengerialRequest::where('status', 'Pending')->count(),
            // Number of facility requests with status = 'Pending'
            'facilityRequestsNotificationCount' => fn () => FacilityRequest::where('status', 'Pending')->count(),
            // Number of service requests with status = 'Pending'
            'serviceRequestsNotificationCount' => fn () => ServiceRequest::where('status', 'Pending')->count(),
            // Number of work requests with status = 'Pending'
            'workRequestsNotificationCount' => fn () => WorkRequest::where('status', 'Pending')->count(),
            // Number of borrowings that are overdue (due_date before today and not yet returned)
            'borrowingsOverdueCount' => fn () => Borrowing::whereNull('return_date')->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::today())->count(),
        ]);
    }

}
