<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\CollectionCategory;
use Illuminate\Support\Facades\Auth;

class LibraryCollectionCategoriesController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 10;

        $q = $request->input('q');
        $query = CollectionCategory::query()->orderBy('name');
        if ($q) {
            $query->where('name', 'like', "%$q%");
        }

        $categories = $query->paginate($perPage)->appends($request->all());

        return Inertia::render('Library/Categories/Index', [
            'categories' => $categories,
            'q' => $q,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150|unique:collection_categories,name',
            'student_borrowing_days' => 'nullable|integer|min:1',
            'employee_borrowing_days' => 'nullable|integer|min:1',
        ], [
            'student_borrowing_days.integer' => 'Student Borrowing Days must be a positive integer.',
            'student_borrowing_days.min' => 'Student Borrowing Days must be at least 1.',
            'employee_borrowing_days.integer' => 'Employee Borrowing Days must be a positive integer.',
            'employee_borrowing_days.min' => 'Employee Borrowing Days must be at least 1.',
        ]);
        $data['created_by'] = Auth::id();
        $category = CollectionCategory::create($data);

        if ($request->header('X-Inertia')) {
            return redirect()->route('library.collection-categories.index')->with('success', 'Category added.');
        }

        return response()->json(['ok' => true, 'category' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = CollectionCategory::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:150|unique:collection_categories,name,'.$category->id,
            'student_borrowing_days' => 'nullable|integer|min:1',
            'employee_borrowing_days' => 'nullable|integer|min:1',
        ], [
            'student_borrowing_days.integer' => 'Student Borrowing Days must be a positive integer.',
            'student_borrowing_days.min' => 'Student Borrowing Days must be at least 1.',
            'employee_borrowing_days.integer' => 'Employee Borrowing Days must be a positive integer.',
            'employee_borrowing_days.min' => 'Employee Borrowing Days must be at least 1.',
        ]);
        $category->update($data);

        if ($request->header('X-Inertia')) {
            return redirect()->route('library.collection-categories.index')->with('success', 'Category updated.');
        }

        return response()->json(['ok' => true, 'category' => $category], 200);
    }

    public function destroy(Request $request, $id)
    {
        $category = CollectionCategory::findOrFail($id);
        $category->delete();

        if ($request->header('X-Inertia')) {
            return redirect()->route('library.collection-categories.index')->with('success', 'Category deleted.');
        }

        return response()->json(['ok' => true], 200);
    }
}
