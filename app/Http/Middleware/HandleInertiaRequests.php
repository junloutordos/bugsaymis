<?php

namespace App\Http\Middleware;

use App\Models\AppVersion;
use App\Models\Borrowing;
use App\Models\Committee;
use App\Models\Consultation;
use App\Models\DocumentRouting;
use App\Models\FacilityRequest;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\ITJobRequest;
use App\Models\MessengerialRequest;
use App\Models\ServiceRequest;
use App\Models\SpecialAssignment;
use App\Models\User;
use App\Models\VehicleRequest;
use App\Models\WorkRequest;
use App\Services\ApprovalInboxService;
use App\Services\FacultyLoading\AdvisoryScheduleScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

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
            $authUser->loadMissing(['roles', 'primaryUnitAssignment.unit:id,name,code,type']);
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
                        'role_id' => $userRoles->pluck('id')->implode(','),
                        'sex' => $authUser->sex,
                        'profile_picture' => $authUser->profile_picture
                            ? $this->s3Url($authUser->profile_picture)
                            : null,
                        'electronic_signature' => $authUser->electronic_signature
                            ? $this->s3Url($authUser->electronic_signature)
                            : null,
                        'has_signature_pin' => ! empty($authUser->signature_pin),
                        'permissions' => $authUser->getPermissions(),
                        'primary_unit' => fn () => $authUser->primaryUnitAssignment?->unit
                            ? [
                                'id' => $authUser->primaryUnitAssignment->unit->id,
                                'name' => $authUser->primaryUnitAssignment->unit->name,
                                'code' => $authUser->primaryUnitAssignment->unit->code,
                                'type' => $authUser->primaryUnitAssignment->unit->type,
                            ]
                            : null,
                    ]
                    : null,
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
            // One-shot flag set at login when the user has no digital signature
            // and/or signing PIN — pulled (consumed) on the first Inertia page
            // render so the setup prompt shows once per login.
            'promptSignatureSetup' => fn () => (bool) $request->session()->pull('prompt_signature_setup', false),
            // ── Sidebar badge counts — cached 60s to reduce DB queries ────────
            'consultationsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.consultations.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        // Clinic/Nurse/Admin see all pending consultations
                        if ($user->hasPermission('health.manage')) {
                            return Consultation::whereIn('status', ['Pending', 'Active'])->count();
                        }

                        // All other roles see only their own consultations
                        return Consultation::whereIn('status', ['Pending', 'Active'])
                            ->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'itJobRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.it_requests.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasPermission('it.requests.manage')) {
                            return ITJobRequest::whereIn('status', ['In Progress'])->count();
                        }

                        return ITJobRequest::whereIn('status', ['In Progress'])->where('user_id', $user->id)->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'vehicleRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.vehicles.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return VehicleRequest::where('status', 'Pending')->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'messengerialRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.messengerial.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasPermission('messengerial.manage')) {
                            return MessengerialRequest::whereNotIn('status', ['Completed', 'Declined'])->count();
                        }

                        return MessengerialRequest::where('email', $user->email)
                            ->whereNotIn('status', ['Completed', 'Declined'])->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'facilityRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.facility.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return FacilityRequest::where('status', 'Pending')->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'serviceRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.service.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return ServiceRequest::where('status', 'Pending')->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'gatepassNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.gatepass.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        $badgeId = User::where('id', $user->id)->value('badge_id');
                        if (! $badgeId) {
                            return 0;
                        }

                        return DB::table('gatepass')
                            ->where('status', 'Pending')
                            ->where('badgeNumber', $badgeId)
                            ->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'workRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.work.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return WorkRequest::where('status', 'Pending')->where('requester_id', $user->id)->count();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'borrowingsOverdueCount' => function () {
                try {
                    return Cache::remember('badge.borrowings_overdue', 300, fn () => Borrowing::whereNull('return_date')->whereNotNull('due_date')
                        ->whereDate('due_date', '<', Carbon::today())->count()
                    );
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'documentTrackingNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }

                    return Cache::remember('badge.documents.u'.$user->id, 60, fn () => DocumentRouting::where('receiver_id', $user->id)
                        ->whereIn('status', ['Pending', 'Received'])->count()
                    );
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'isAUH' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return false;
                }

                return Cache::remember('badge.auh.u'.$user->id, 300, fn () => AcademicUnit::where('head_user_id', $user->id)->where('is_active', true)->exists()
                );
            },
            'hasAdvisoryScheduleScope' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return false;
                }

                try {
                    return app(AdvisoryScheduleScopeService::class)->hasCurrentScope($user);
                } catch (\Throwable) {
                    return false;
                }
            },
            'isPMRater' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return false;
                }

                return Cache::remember('badge.pm_rater.u'.$user->id, 300, fn () => Committee::where('head_id', $user->id)->exists()
                        || SpecialAssignment::where('coordinator_id', $user->id)->exists()
                );
            },
            'approvalInboxCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (! $user) {
                        return 0;
                    }
                    $cacheKey = 'badge.approvals_inbox.u'.$user->id;

                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return (new ApprovalInboxService($user))->totalPendingCount();
                    });
                } catch (\Throwable $e) {
                    return 0;
                }
            },
            'appVersion' => function () {
                try {
                    return Cache::remember('app.version', 3600, function () {
                        $versions = AppVersion::where('is_visible', true)
                            ->orderBy('date', 'desc')->orderBy('id', 'desc')->get();
                        $current = $versions->firstWhere('is_current', true) ?? $versions->first();

                        return [
                            'current' => $current?->version ?? '1.0.0',
                            'history' => $versions->map(fn ($v) => [
                                'id' => $v->id,
                                'version' => $v->version,
                                'date' => $v->date->format('Y-m-d'),
                                'remarks' => $v->remarks,
                                'changes' => $v->changes,
                            ])->values()->toArray(),
                        ];
                    });
                } catch (\Throwable $e) {
                    return ['current' => '1.0.0', 'history' => []];
                }
            },
            'atlasGoVersion' => config('atlasgo.mobile_version'),
        ]);
    }

    private function s3Url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addHour());
        } catch (\Throwable) {
            return route('storage.proxy', ['path' => $path]);
        }
    }
}
