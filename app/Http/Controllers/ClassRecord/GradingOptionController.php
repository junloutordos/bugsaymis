<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradingOptionController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'CID Chief']);
    }

    /**
     * GET /grading-options
     * List ALL grading options with their categories (admin sees inactive too).
     */
    public function index(): JsonResponse
    {
        $query = GradingOption::with(['categories' => fn ($q) => $q->orderBy('sort_order')]);

        if (! $this->isAdmin()) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('id')->get());
    }

    /**
     * PUT /grading-options/{gradingOption}
     * Update the option's name, description, and is_active flag.
     */
    public function update(Request $request, GradingOption $gradingOption): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can edit grading options.');

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        $gradingOption->update($validated);

        return response()->json([
            'message' => 'Grading option updated.',
            'data'    => $gradingOption->fresh()->load('categories'),
        ]);
    }

    /**
     * PUT /grading-options/{gradingOption}/categories
     * Bulk upsert the categories for one grading option.
     * Blocks deletion of categories that already have assessments in a class record.
     */
    public function updateCategories(Request $request, GradingOption $gradingOption): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can edit grading option categories.');

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
            'data'    => $gradingOption->fresh()->load('categories'),
        ]);
    }
}
