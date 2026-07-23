<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Services\ClassRecord\GradingOptionScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradingOptionController extends Controller
{
    public function __construct(private readonly GradingOptionScopeService $scope)
    {
    }

    private function canManageOptions(): bool
    {
        return $this->scope->canManage(Auth::user());
    }

    /**
     * GET /grading-options
     * List ALL grading options with their categories (managers see inactive too).
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $options = $this->canManageOptions()
            ? $this->scope->manageableFor($user)
            : $this->scope->selectableForUser($user);

        return response()->json($options);
    }

    /**
     * POST /grading-options
     * Create a new grading option with its initial categories.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManageOptions(), 403, 'You are not allowed to create grading options.');

        $validated = $request->validate([
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string|max:1000',
            'is_active'                    => 'boolean',
            'owner_designation_id'         => 'nullable|integer|exists:designations,id',
            'categories'                   => 'required|array|min:1',
            'categories.*.name'            => 'required|string|max:255',
            'categories.*.code'            => 'required|string|max:10',
            'categories.*.weight'          => 'required|numeric|min:0.001|max:1',
            'categories.*.max_assessments' => 'required|integer|min:1|max:20',
            'categories.*.sort_order'      => 'required|integer|min:0',
        ]);

        $ownerDesignationId = $this->scope->resolveOwnerDesignationId(
            Auth::user(),
            isset($validated['owner_designation_id']) ? (int) $validated['owner_designation_id'] : null,
        );
        $this->ensureUniqueName($validated['name'], $ownerDesignationId);

        $totalWeight = array_sum(array_column($validated['categories'], 'weight'));
        if (abs($totalWeight - 1.0) > 0.005) {
            return response()->json([
                'message' => 'Category weights must sum to 100%. Current total: ' . round($totalWeight * 100, 1) . '%.',
                'errors'  => ['categories' => ['Weights must sum to 100%.']],
            ], 422);
        }

        $option = DB::transaction(function () use ($validated, $ownerDesignationId) {
            $option = GradingOption::create([
                'name'                 => $validated['name'],
                'description'          => $validated['description'] ?? null,
                'is_active'            => $validated['is_active'] ?? true,
                'owner_designation_id' => $ownerDesignationId,
            ]);

            foreach ($validated['categories'] as $i => $cat) {
                GradingCategory::create([
                    'grading_option_id' => $option->id,
                    'name'              => $cat['name'],
                    'code'              => strtoupper($cat['code']),
                    'weight'            => $cat['weight'],
                    'max_assessments'   => $cat['max_assessments'],
                    'sort_order'        => $cat['sort_order'] ?? $i,
                ]);
            }

            return $option;
        });

        return response()->json([
            'message' => 'Grading option created.',
            'data'    => $option->fresh()->load(['categories', 'ownerDesignation:id,code,name']),
        ], 201);
    }

    /**
     * DELETE /grading-options/{gradingOption}
     * Delete a grading option. Blocked if any class record uses it.
     */
    public function destroy(GradingOption $gradingOption): JsonResponse
    {
        abort_unless(
            $this->scope->canManageOption(Auth::user(), $gradingOption),
            403,
            'You may only delete grading options owned by your academic unit.',
        );

        $inUse = \App\Models\ClassRecord\ClassRecord::where('grading_option_id', $gradingOption->id)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'Cannot delete this grading option — it is used by one or more class records.',
            ], 422);
        }

        $gradingOption->delete();

        return response()->json(['message' => 'Grading option deleted.']);
    }

    /**
     * PUT /grading-options/{gradingOption}
     * Update the option's name, description, and is_active flag.
     */
    public function update(Request $request, GradingOption $gradingOption): JsonResponse
    {
        abort_unless(
            $this->scope->canManageOption(Auth::user(), $gradingOption),
            403,
            'You may only edit grading options owned by your academic unit.',
        );

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);
        $this->ensureUniqueName(
            $validated['name'],
            $gradingOption->owner_designation_id,
            (int) $gradingOption->id,
        );

        $gradingOption->update($validated);

        return response()->json([
            'message' => 'Grading option updated.',
            'data'    => $gradingOption->fresh()->load(['categories', 'ownerDesignation:id,code,name']),
        ]);
    }

    /**
     * PUT /grading-options/{gradingOption}/categories
     * Bulk upsert the categories for one grading option.
     * Blocks deletion of categories that already have assessments in a class record.
     */
    public function updateCategories(Request $request, GradingOption $gradingOption): JsonResponse
    {
        abort_unless(
            $this->scope->canManageOption(Auth::user(), $gradingOption),
            403,
            'You may only edit grading options owned by your academic unit.',
        );

        $validated = $request->validate([
            'categories'                   => 'required|array|min:1',
            'categories.*.id'              => 'nullable|integer',
            'categories.*.name'            => 'required|string|max:255',
            'categories.*.code'            => 'required|string|max:10',
            'categories.*.weight'          => 'required|numeric|min:0.001|max:1',
            'categories.*.max_assessments' => 'required|integer|min:1|max:20',
            'categories.*.sort_order'      => 'required|integer|min:0',
        ]);

        // Weights must sum to 1.0 (allow ±0.001 for floating point)
        $totalWeight = array_sum(array_column($validated['categories'], 'weight'));
        if (abs($totalWeight - 1.0) > 0.005) {
            return response()->json([
                'message' => 'Category weights must sum to 100%. Current total: ' . round($totalWeight * 100, 1) . '%.',
                'errors'  => ['categories' => ['Weights must sum to 100%.']],
            ], 422);
        }

        // Find incoming category IDs (existing rows being kept)
        $incomingIds = collect($validated['categories'])
            ->pluck('id')
            ->filter()
            ->toArray();

        // Check if any categories being removed have existing assessments
        $existingCatIds = $gradingOption->categories->pluck('id')->toArray();
        $removedIds     = array_diff($existingCatIds, $incomingIds);

        if ($removedIds) {
            $inUse = ClassRecordAssessment::whereIn('grading_category_id', $removedIds)->exists();
            if ($inUse) {
                return response()->json([
                    'message' => 'Cannot remove categories that already have assessments in a class record. Clear those assessments first.',
                    'errors'  => ['categories' => ['One or more categories are in use.']],
                ], 422);
            }
        }

        DB::transaction(function () use ($gradingOption, $validated, $removedIds) {
            // Delete removed categories
            if ($removedIds) {
                GradingCategory::whereIn('id', $removedIds)->delete();
            }

            // Upsert each incoming category
            foreach ($validated['categories'] as $i => $cat) {
                GradingCategory::updateOrCreate(
                    [
                        'id' => $cat['id'] ?? 0,
                    ],
                    [
                        'grading_option_id' => $gradingOption->id,
                        'name'              => $cat['name'],
                        'code'              => strtoupper($cat['code']),
                        'weight'            => $cat['weight'],
                        'max_assessments'   => $cat['max_assessments'],
                        'sort_order'        => $cat['sort_order'] ?? $i,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Categories updated.',
            'data'    => $gradingOption->fresh()->load(['categories', 'ownerDesignation:id,code,name']),
        ]);
    }

    private function ensureUniqueName(string $name, ?int $ownerDesignationId, ?int $ignoreId = null): void
    {
        $query = GradingOption::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when(
                $ownerDesignationId === null,
                fn ($scopeQuery) => $scopeQuery->whereNull('owner_designation_id'),
                fn ($scopeQuery) => $scopeQuery->where('owner_designation_id', $ownerDesignationId),
            )
            ->when($ignoreId, fn ($scopeQuery) => $scopeQuery->where('id', '<>', $ignoreId));

        if ($query->exists()) {
            abort(422, 'A grading option with this name already exists in the selected scope.');
        }
    }
}
