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
     * Create a new grading option with its initial (possibly nested) categories.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManageOptions(), 403, 'You are not allowed to create grading options.');

        $validated = $this->validatePayload($request, requireCategories: true);

        $ownerDesignationId = $this->scope->resolveOwnerDesignationId(
            Auth::user(),
            isset($validated['owner_designation_id']) ? (int) $validated['owner_designation_id'] : null,
        );
        $this->ensureUniqueName($validated['name'], $ownerDesignationId);

        if (($error = $this->validateLeafWeights($validated['categories'])) !== null) {
            return $error;
        }

        $option = DB::transaction(function () use ($validated, $ownerDesignationId) {
            $option = GradingOption::create([
                'name'                 => $validated['name'],
                'description'          => $validated['description'] ?? null,
                'is_active'            => $validated['is_active'] ?? true,
                'applicable_quarters'  => $this->normalizeQuarters($validated['applicable_quarters'] ?? null),
                'owner_designation_id' => $ownerDesignationId,
            ]);

            $this->syncCategories($option, $validated['categories']);

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

        $inUse = \App\Models\ClassRecord\ClassRecord::where('grading_option_id', $gradingOption->id)->exists()
            || \App\Models\ClassRecord\ClassRecordQuarter::where('grading_option_id', $gradingOption->id)->exists();
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
            'applicable_quarters'   => 'nullable|array',
            'applicable_quarters.*' => 'integer|in:1,2,3,4',
        ]);
        $this->ensureUniqueName(
            $validated['name'],
            $gradingOption->owner_designation_id,
            (int) $gradingOption->id,
        );

        $gradingOption->update([
            'name'                => $validated['name'],
            'description'         => $validated['description'] ?? null,
            'is_active'           => $validated['is_active'] ?? $gradingOption->is_active,
            'applicable_quarters' => $this->normalizeQuarters($validated['applicable_quarters'] ?? null),
        ]);

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

        $validated = $this->validatePayload($request, requireCategories: true);

        if (($error = $this->validateLeafWeights($validated['categories'])) !== null) {
            return $error;
        }

        // Block removal of any category that already has assessments.
        $incomingIds = $this->incomingCategoryIds($validated['categories']);
        $existingIds = $gradingOption->categories->pluck('id')->all();
        $removedIds  = array_diff($existingIds, $incomingIds);

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
            if ($removedIds) {
                GradingCategory::whereIn('id', $removedIds)->delete();
            }
            $this->syncCategories($gradingOption, $validated['categories']);
        });

        return response()->json([
            'message' => 'Categories updated.',
            'data'    => $gradingOption->fresh()->load(['categories', 'ownerDesignation:id,code,name']),
        ]);
    }

    // ── Shared category/quarter helpers ───────────────────────────────────────

    /**
     * Validate a create/updateCategories payload. Categories may be flat
     * (leaf: weight + max_assessments) or nested (a parent with `children`,
     * each child a leaf). One level of nesting is supported.
     *
     * @return array validated data
     */
    private function validatePayload(Request $request, bool $requireCategories): array
    {
        $rules = [
            'name'                                    => 'required|string|max:255',
            'description'                             => 'nullable|string|max:1000',
            'is_active'                               => 'boolean',
            'owner_designation_id'                    => 'nullable|integer|exists:designations,id',
            'applicable_quarters'                     => 'nullable|array',
            'applicable_quarters.*'                   => 'integer|in:1,2,3,4',
            'categories'                              => ($requireCategories ? 'required' : 'nullable').'|array|min:1',
            'categories.*.id'                         => 'nullable|integer',
            'categories.*.name'                       => 'required|string|max:255',
            'categories.*.code'                       => 'required|string|max:10',
            'categories.*.sort_order'                 => 'required|integer|min:0',
            'categories.*.weight'                     => 'nullable|numeric|min:0|max:1',
            'categories.*.max_assessments'            => 'nullable|integer|min:1|max:20',
            'categories.*.children'                   => 'nullable|array',
            'categories.*.children.*.id'              => 'nullable|integer',
            'categories.*.children.*.name'            => 'required|string|max:255',
            'categories.*.children.*.code'            => 'required|string|max:10',
            'categories.*.children.*.weight'          => 'required|numeric|min:0.0001|max:1',
            'categories.*.children.*.max_assessments' => 'required|integer|min:1|max:20',
            'categories.*.children.*.sort_order'      => 'required|integer|min:0',
        ];

        $validated = $request->validate($rules);

        // A top-level category with no children is a leaf and must carry its
        // own weight + max_assessments.
        foreach ($validated['categories'] ?? [] as $index => $cat) {
            if (empty($cat['children'])) {
                if (! isset($cat['weight']) || ! isset($cat['max_assessments'])) {
                    abort(422, "Category \"{$cat['name']}\" must have a weight and max items, or contain sub-categories.");
                }
            }
        }

        return $validated;
    }

    /** Flatten a nested payload to its leaves (children, or childless top-level). */
    private function extractLeaves(array $categories): array
    {
        $leaves = [];
        foreach ($categories as $cat) {
            $children = $cat['children'] ?? [];
            if (! empty($children)) {
                foreach ($children as $child) {
                    $leaves[] = $child;
                }
            } else {
                $leaves[] = $cat;
            }
        }

        return $leaves;
    }

    private function validateLeafWeights(array $categories): ?JsonResponse
    {
        $total = array_sum(array_map(fn ($leaf) => (float) ($leaf['weight'] ?? 0), $this->extractLeaves($categories)));
        if (abs($total - 1.0) > 0.005) {
            return response()->json([
                'message' => 'Category weights must sum to 100%. Current total: '.round($total * 100, 1).'%.',
                'errors'  => ['categories' => ['Weights must sum to 100% across all leaf categories/sub-categories.']],
            ], 422);
        }

        return null;
    }

    private function incomingCategoryIds(array $categories): array
    {
        $ids = [];
        foreach ($categories as $cat) {
            if (! empty($cat['id'])) {
                $ids[] = (int) $cat['id'];
            }
            foreach ($cat['children'] ?? [] as $child) {
                if (! empty($child['id'])) {
                    $ids[] = (int) $child['id'];
                }
            }
        }

        return $ids;
    }

    /** Persist top-level categories and their sub-categories with parent links. */
    private function syncCategories(GradingOption $option, array $categories): void
    {
        foreach ($categories as $i => $cat) {
            $children = $cat['children'] ?? [];
            $isParent = ! empty($children);

            $parent = GradingCategory::updateOrCreate(
                ['id' => $cat['id'] ?? 0],
                [
                    'grading_option_id' => $option->id,
                    'parent_id'         => null,
                    'name'              => $cat['name'],
                    'code'              => strtoupper($cat['code']),
                    // A parent's stored weight is the sum of its children (display only).
                    'weight'            => $isParent
                        ? array_sum(array_map(fn ($c) => (float) $c['weight'], $children))
                        : (float) $cat['weight'],
                    'max_assessments'   => $isParent ? 0 : (int) $cat['max_assessments'],
                    'sort_order'        => $cat['sort_order'] ?? $i,
                ],
            );

            foreach ($children as $j => $child) {
                GradingCategory::updateOrCreate(
                    ['id' => $child['id'] ?? 0],
                    [
                        'grading_option_id' => $option->id,
                        'parent_id'         => $parent->id,
                        'name'              => $child['name'],
                        'code'              => strtoupper($child['code']),
                        'weight'            => (float) $child['weight'],
                        'max_assessments'   => (int) $child['max_assessments'],
                        'sort_order'        => $child['sort_order'] ?? $j,
                    ],
                );
            }
        }
    }

    /** Empty/full selection → null (applies to all quarters); otherwise sorted unique set. */
    private function normalizeQuarters(?array $quarters): ?array
    {
        if (empty($quarters)) {
            return null;
        }
        $set = collect($quarters)->map(fn ($q) => (int) $q)->unique()->sort()->values()->all();

        return count($set) >= 4 ? null : $set;
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
