<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\OrgUnit\StoreVersionRequest;
use App\Models\OrganizationalVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrganizationalVersionController extends Controller
{
    /**
     * GET /hr/org/versions
     * Inertia page listing all org structure versions.
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('org.versions.view');

        $versions = OrganizationalVersion::with('creator:id,name', 'approver:id,name')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('HR/OrgStructure/Versions', [
            'versions' => $versions,
            'current'  => OrganizationalVersion::current(),
            'can'      => [
                'manage' => $request->user()->can('org.versions.manage'),
            ],
        ]);
    }

    /**
     * GET /hr/org/versions/current
     * The currently active version (public snapshot).
     */
    public function current(): JsonResponse
    {
        $this->authorize('org.view');

        $version = OrganizationalVersion::current();

        return response()->json($version);
    }

    /**
     * GET /hr/org/versions/{version}
     * Single version detail with snapshot.
     */
    public function show(OrganizationalVersion $version): JsonResponse
    {
        $this->authorize('org.versions.view');

        $version->load('creator:id,name', 'approver:id,name');

        return response()->json($version);
    }

    /**
     * POST /hr/org/versions
     * Create a new draft version.
     */
    public function store(StoreVersionRequest $request): JsonResponse
    {
        $version = DB::transaction(function () use ($request) {
            return OrganizationalVersion::create(array_merge(
                $request->validated(),
                ['status' => OrganizationalVersion::STATUS_DRAFT]
            ));
        });

        return response()->json($version, 201);
    }

    /**
     * POST /hr/org/versions/{version}/approve
     * Move a draft to "approved" status.
     */
    public function approve(Request $request, OrganizationalVersion $version): JsonResponse
    {
        $this->authorize('org.versions.manage');

        if ($version->status !== OrganizationalVersion::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft versions can be approved.'], 422);
        }

        DB::transaction(function () use ($version) {
            $version->update([
                'status'      => OrganizationalVersion::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return response()->json($version->fresh());
    }

    /**
     * POST /hr/org/versions/{version}/activate
     * Activate an approved version:
     *  1. Archives the current active version.
     *  2. Captures a JSON snapshot of the live org tree.
     *  3. Sets this version as active.
     */
    public function activate(Request $request, OrganizationalVersion $version): JsonResponse
    {
        $this->authorize('org.versions.manage');

        if ($version->status === OrganizationalVersion::STATUS_ACTIVE) {
            return response()->json(['message' => 'This version is already active.'], 422);
        }

        if ($version->status === OrganizationalVersion::STATUS_ARCHIVED) {
            return response()->json(['message' => 'Archived versions cannot be re-activated.'], 422);
        }

        DB::transaction(function () use ($version) {
            $version->activate();
        });

        return response()->json($version->fresh());
    }

    /**
     * DELETE /hr/org/versions/{version}
     * Soft-delete a draft version.
     */
    public function destroy(OrganizationalVersion $version): JsonResponse
    {
        $this->authorize('org.versions.manage');

        if (in_array($version->status, [
            OrganizationalVersion::STATUS_ACTIVE,
            OrganizationalVersion::STATUS_APPROVED,
        ])) {
            return response()->json([
                'message' => 'Only draft versions may be deleted. Archive or supersede active/approved versions instead.',
            ], 422);
        }

        DB::transaction(fn () => $version->delete());

        return response()->json(['message' => 'Draft version deleted.']);
    }
}
