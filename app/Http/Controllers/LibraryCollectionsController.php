<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\LibraryCollection;
use App\Models\CollectionCategory;
use Illuminate\Support\Facades\Auth;

class LibraryCollectionsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 25;
        $q = $request->input('q');
        $sort = $request->input('sort', 'title');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $filterType = $request->input('collection_type');
        $filterStatus = $request->input('status');
        $query = LibraryCollection::query()->orderBy('title');

        // apply filters
        if ($filterType) {
            $query->where('collection_type', $filterType);
        }
        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }

        // apply search
        if ($q) {
            $query->where(function($qr) use ($q) {
                $qr->where('title', 'like', "%$q%")
                   ->orWhere('author_publisher', 'like', "%$q%")
                   ->orWhere('accession_number', 'like', "%$q%")
                   ->orWhere('category', 'like', "%$q%");
            });
        }

        // apply sorting if allowed
        $allowedSorts = ['title','collection_type','accession_number','category','status','created_at'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        }

        $collections = $query->paginate($perPage)->appends($request->only('q','sort','direction','collection_type','status'));

        $categories = CollectionCategory::orderBy('name')->get();

        return Inertia::render('Library/Collections/Index', [
            'collections' => $collections,
            'q' => $q,
            'sort' => $sort,
            'direction' => $direction,
            'filters' => [
                'collection_type' => $filterType,
                'status' => $filterStatus,
            ],
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'collection_type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'author_publisher' => 'nullable|string|max:255',
            'accession_number' => 'nullable|string|max:100|unique:library_collections,accession_number',
            'call_number' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
        ]);

        $data['created_by'] = Auth::id();
        $collection = LibraryCollection::create($data);

        // If this was an Inertia request (X-Inertia header), return a redirect so Inertia can handle it.
        if ($request->header('X-Inertia')) {
            return redirect()->route('library.collections.index')->with('success', 'Collection added.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'collection' => $collection], 201);
        }

        return redirect()->route('library.collections.index')->with('success', 'Collection added.');
    }

    public function update(Request $request, $id)
    {
        $collection = LibraryCollection::findOrFail($id);
        $data = $request->validate([
            'collection_type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'author_publisher' => 'nullable|string|max:255',
            'accession_number' => 'nullable|string|max:100|unique:library_collections,accession_number,'.$collection->id,
            'call_number' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
        ]);

        $collection->update($data);

        if ($request->header('X-Inertia')) {
            return redirect()->route('library.collections.index')->with('success', 'Collection updated.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'collection' => $collection], 200);
        }

        return redirect()->route('library.collections.index')->with('success', 'Collection updated.');
    }

    public function destroy(Request $request, $id)
    {
        $collection = LibraryCollection::findOrFail($id);
        $collection->delete();

        if ($request->header('X-Inertia')) {
            return redirect()->route('library.collections.index')->with('success', 'Collection deleted.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true], 200);
        }

        return redirect()->route('library.collections.index')->with('success', 'Collection deleted.');
    }
}
