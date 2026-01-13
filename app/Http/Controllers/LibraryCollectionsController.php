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
        $perPage = 10;
        $q = $request->input('q');
        $sort = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $filterType = $request->input('collection_type');
        $filterStatus = $request->input('status');
        $query = LibraryCollection::query()->orderBy('id');

        // apply filters
        if ($filterType) {
            $query->where('collection_type', $filterType);
        }
        // if a category filter is provided, use it
        $filterCategory = $request->input('category');
        if ($filterCategory) {
            $query->where('category', $filterCategory);
        }

        // apply search
        if ($q) {
            $query->where(function($qr) use ($q) {
                     $qr->where('title', 'like', "%$q%")
                         ->orWhere('author_publisher', 'like', "%$q%")
                         ->orWhere('edition', 'like', "%$q%");
            });
        }

        // apply sorting if allowed
        $allowedSorts = ['id','title','collection_type','edition','category','publisher','created_at'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        }

        $collections = $query->paginate($perPage)->appends($request->only('q','sort','direction','collection_type','category'));

        // Prefer real `volume` column if present, otherwise alias `collection_type` for frontend
        $collections->getCollection()->transform(function ($c) {
            $c->volume = $c->volume ?? $c->collection_type;
            // expose edition field for frontend
            $c->edition = $c->edition ?? null;
            return $c;
        });

        $categories = CollectionCategory::orderBy('name')->get();

        return Inertia::render('Library/Collections/Index', [
            'collections' => $collections,
            'q' => $q,
            'sort' => $sort,
            'direction' => $direction,
            'filters' => [
                'collection_type' => $filterType,
                'category' => $filterCategory,
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
            'call_number' => 'nullable|string|max:100',
            'edition' => 'nullable|string|max:255',
            'year' => ['nullable','digits:4'],
            'isbn' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
        ]);

        // accept `collection_type` primarily, but fall back to legacy `volume` if present
        $data['collection_type'] = $data['collection_type'] ?? $data['volume'] ?? null;
        // ensure status has a safe default if not provided
        $data['status'] = $data['status'] ?? 'Available';

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
                'call_number' => 'nullable|string|max:100',
                'edition' => 'nullable|string|max:255',
                'year' => ['nullable','digits:4'],
                'isbn' => 'nullable|string|max:50',
                'subject' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'publisher' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:50',
            ]);

        // accept `collection_type` primarily, but fall back to legacy `volume` if present
        $data['collection_type'] = $data['collection_type'] ?? $data['volume'] ?? null;
        // keep edition column in data if present
        if (isset($data['edition'])) {
            $data['edition'] = $data['edition'];
        }
            $data['status'] = $data['status'] ?? 'Available';

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

    /**
     * Return a single collection as JSON (used by the edit modal to fetch fresh data).
     */
    public function show($id)
    {
        $collection = LibraryCollection::findOrFail($id);
        // prefer volume column if present
        $collection->volume = $collection->volume ?? $collection->collection_type;
        return response()->json($collection);
    }
}
