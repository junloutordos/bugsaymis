<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\GradingOption;
use Illuminate\Http\JsonResponse;

class GradingOptionController extends Controller
{
    /**
     * GET /grading-options
     * List all active grading options with their categories.
     */
    public function index(): JsonResponse
    {
        $options = GradingOption::with(['categories' => fn ($q) => $q->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return response()->json($options);
    }
}
