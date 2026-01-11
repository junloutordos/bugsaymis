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
        $categories = CollectionCategory::orderBy('name')->get();
        return Inertia::render('Library/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150|unique:collection_categories,name',
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
