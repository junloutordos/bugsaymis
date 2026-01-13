<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Borrowing;
use App\Models\LibraryCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LibraryBorrowingsController extends Controller
{
    protected function checkAccess()
    {
        $user = Auth::user();
        $role = $user->role->name ?? null;
        return in_array($role, ['Administrator', 'Librarian']);
    }

    public function index(Request $request)
    {
        if (! $this->checkAccess()) {
            abort(403);
        }

        $q = $request->input('q');

        $query = Borrowing::with(['collection', 'processedBy'])->orderBy('created_at', 'desc');

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('borrower_id', 'like', "%{$q}%")
                   ->orWhereHas('collection', function ($c) use ($q) {
                       $c->where('title', 'like', "%{$q}%")
                         ->orWhere('call_number', 'like', "%{$q}%");
                   })
                   ->orWhere('borrow_date', 'like', "%{$q}%")
                   ->orWhere('due_date', 'like', "%{$q}%")
                   ->orWhere('return_date', 'like', "%{$q}%")
                   ->orWhere('status', 'like', "%{$q}%");
            });

            // additionally try to resolve student ids by pisay or name and include them
            try {
                $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
                $pisayCandidates = ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id','pisayid','pisay_systemid'];
                $pisayCol = null;
                foreach ($pisayCandidates as $cname) { if (in_array($cname, $cols)) { $pisayCol = $cname; break; } }

                $studentQuery = DB::table('students');
                if ($pisayCol) {
                    $studentQuery->orWhere($pisayCol, 'like', "%{$q}%");
                }
                // search common name fields
                foreach (['first_name','firstname','fname','given_name','last_name','lastname','lname','surname'] as $nf) {
                    if (in_array($nf, $cols)) {
                        $studentQuery->orWhere($nf, 'like', "%{$q}%");
                    }
                }
                $studentIds = $studentQuery->pluck('id')->toArray();
                if (! empty($studentIds)) {
                    $query->orWhere(function ($qb2) use ($studentIds) {
                        $qb2->whereIn('borrower_id', $studentIds)->where('borrower_type', 'student');
                    });
                }
            } catch (\Throwable $e) {
                // ignore student resolution errors
            }

            // also try resolving employee/user ids by name for borrower matching
            try {
                $userCols = collect(DB::select("SHOW COLUMNS FROM users"))->map(fn($c) => $c->Field)->all();
                $userQuery = DB::table('users');
                if (in_array('name', $userCols)) {
                    $userQuery->orWhere('name', 'like', "%{$q}%");
                }
                foreach (['first_name','firstname','fname','given_name','last_name','lastname','lname','surname'] as $nf) {
                    if (in_array($nf, $userCols)) {
                        $userQuery->orWhere($nf, 'like', "%{$q}%");
                    }
                }
                $userIds = $userQuery->pluck('id')->toArray();
                if (! empty($userIds)) {
                    $query->orWhere(function ($qb3) use ($userIds) {
                        $qb3->whereIn('borrower_id', $userIds)->where('borrower_type', 'employee');
                    });
                }
            } catch (\Throwable $e) {
                // ignore user resolution errors
            }
        }

        $borrowings = $query->paginate(25)->appends($request->all());

        // Enrich each borrowing with a resolved borrower name for display
        $borrowings->getCollection()->transform(function ($b) {
            $b->borrower_name = null;
            try {
                $type = strtolower($b->borrower_type ?? '');
                if ($type === 'student') {
                    $student = DB::table('students')->where('id', $b->borrower_id)->first();
                    if ($student) {
                        // attempt to get first/last name fields
                        $first = null; $last = null;
                        foreach (['first_name','firstname','fname','given_name'] as $c) if (isset($student->$c)) { $first = $student->$c; break; }
                        foreach (['last_name','lastname','lname','surname'] as $c) if (isset($student->$c)) { $last = $student->$c; break; }
                        if ($first || $last) $b->borrower_name = trim(($last ? $last.' ' : '').($first ?? ''));
                        else $b->borrower_name = trim(implode(' ', array_filter((array)$student)));
                    }
                } elseif ($type === 'employee') {
                    // resolve employee borrower from users table (staff/faculty/etc.)
                    $emp = DB::table('users')->where('id', $b->borrower_id)->first();
                    if ($emp) {
                        // prefer single `name` column if present
                        if (isset($emp->name) && $emp->name) {
                            $b->borrower_name = $emp->name;
                        } else {
                            $first = null; $last = null;
                            foreach (['first_name','firstname','fname','given_name'] as $c) if (isset($emp->$c)) { $first = $emp->$c; break; }
                            foreach (['last_name','lastname','lname','surname'] as $c) if (isset($emp->$c)) { $last = $emp->$c; break; }
                            if ($first || $last) $b->borrower_name = trim(($last ? $last.' ' : '').($first ?? ''));
                            else $b->borrower_name = trim(implode(' ', array_filter((array)$emp)));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore resolution errors
            }
            if (! $b->borrower_name) $b->borrower_name = ($b->borrower_type ?? 'Unknown').' #'.$b->borrower_id;
            // Present borrower name in ALL CAPS for consistency in the table
            try { $b->borrower_name = strtoupper($b->borrower_name); } catch (\Throwable $e) { }
            return $b;
        });

        // Prepare employees list for dropdown (id + display name)
        $employees = [];
        try {
            $emps = DB::table('users')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->whereNotIn('roles.name', ['Student', 'Parent'])
                ->select('users.*')
                ->orderBy('users.name', 'asc')
                ->get();

            foreach ($emps as $emp) {
                // prefer a single `name` column if present
                $name = $emp->name ?? null;
                if (! $name) {
                    $first = null; $last = null;
                    foreach (['first_name','firstname','fname','given_name'] as $c) if (isset($emp->$c)) { $first = $emp->$c; break; }
                    foreach (['last_name','lastname','lname','surname'] as $c) if (isset($emp->$c)) { $last = $emp->$c; break; }
                    if ($first || $last) {
                        $name = trim(($last ? $last.' ' : '').($first ?? ''));
                    } else {
                        $arr = array_filter((array)$emp);
                        $name = trim(implode(' ', $arr));
                    }
                }

                if (! $name) $name = 'Employee #'.$emp->id;
                $employees[] = ['id' => $emp->id, 'name' => $name];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        // ensure alphabetical order by name (case-insensitive)
        try {
            usort($employees, function ($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        return Inertia::render('Library/Borrowings/Index', [
            'borrowings' => $borrowings,
            'q' => $q,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        Log::info('LibraryBorrowingsController@store called', ['user_id' => optional(Auth::user())->id, 'payload' => $request->all()]);
        try {
            if (! $this->checkAccess()) {
                Log::warning('Unauthorized attempt to create borrowing', ['user_id' => optional(Auth::user())->id]);
                return Redirect::route('library.borrowings.index')->with('error', 'Unauthorized');
            }

            $data = $request->validate([
                'collection_id' => 'required|exists:library_collections,id',
                'borrower_type' => 'required|string',
                'borrower_id' => 'required',
                'remarks' => 'nullable|string',
            ]);

        // If borrower_type is student, allow providing Pisay System ID and resolve to student PK
        if (strtolower($data['borrower_type']) === 'student') {
            $input = $data['borrower_id'];

            // try direct numeric id first
            $student = null;
            if (is_numeric($input)) {
                $student = DB::table('students')->where('id', $input)->first();
            }

            if (! $student) {
                // discover possible pisay column names
                $cols = collect(DB::select("SHOW COLUMNS FROM students"))->map(fn($c) => $c->Field)->all();
                $pisayCandidates = ['pisaysystemid','pisaysystemID','pisaysystem_id','pisay_system_id','pisay_id','pisayid','pisay_systemid'];
                $pisayCol = null;
                foreach ($pisayCandidates as $c) {
                    if (in_array($c, $cols)) { $pisayCol = $c; break; }
                }

                if ($pisayCol) {
                    $student = DB::table('students')->where($pisayCol, $input)->first();
                } else {
                    $student = DB::table('students')->whereRaw('LOWER(CONCAT_WS(" ", '.implode(', ', array_map(fn($c)=>"`$c`", $cols)).')) LIKE ?', ["%".strtolower($input)."%"])->first();
                }
            }

            if (! $student) {
                Log::warning('Student not found for borrower input', ['input' => $input]);
                return Redirect::route('library.borrowings.index')->with('error', 'Student not found for provided Pisay ID.');
            }

            $data['borrower_id'] = $student->id;
        }

        Log::info('Borrowing data after resolution', ['data' => $data]);

        // Ensure collection is available (explicitly disallow if currently Borrowed)
        $collection = LibraryCollection::findOrFail($data['collection_id']);
        $status = $collection->status ?? 'Available';
        if (strcasecmp($status, 'Borrowed') === 0) {
            Log::warning('Collection currently borrowed', ['collection_id' => $collection->id, 'status' => $collection->status]);
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return Redirect::back()->withErrors(['collection_id' => ['Collection is currently borrowed.']])->withInput();
            }
            return Redirect::route('library.borrowings.index')->with('error', 'Collection is currently borrowed.');
        }

        // Prevent multiple active borrowings for same collection
        $active = Borrowing::where('collection_id', $collection->id)->whereNull('return_date')->where('status', 'Borrowed')->exists();
        if ($active) {
            Log::warning('Active borrowing exists for collection', ['collection_id' => $collection->id]);
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return Redirect::back()->withErrors(['collection_id' => ['Collection already has an active borrowing.']])->withInput();
            }
            return Redirect::route('library.borrowings.index')->with('error', 'Collection already has an active borrowing.');
        }

        // Calculate dates based on category rules (category-level limits take precedence)
        $borrowDate = Carbon::now()->toDateString();
        $borrowerType = strtolower($data['borrower_type']);

        // Attempt to resolve a configured category record and use its limits if present
        $categoryName = trim((string)($collection->category ?? ''));
        $categoryRecord = null;
        try {
            if ($categoryName !== '') {
                $categoryRecord = \App\Models\CollectionCategory::where('name', $categoryName)->first();
            }
        } catch (\Throwable $e) {
            // ignore lookup errors
            $categoryRecord = null;
        }

        $dueDate = null;
        if ($categoryRecord) {
            if ($borrowerType === 'student' && $categoryRecord->student_borrowing_days && intval($categoryRecord->student_borrowing_days) > 0) {
                $dueDate = Carbon::now()->addDays(intval($categoryRecord->student_borrowing_days))->toDateString();
            } elseif ($borrowerType === 'employee' && $categoryRecord->employee_borrowing_days && intval($categoryRecord->employee_borrowing_days) > 0) {
                $dueDate = Carbon::now()->addDays(intval($categoryRecord->employee_borrowing_days))->toDateString();
            }
        }

        // If category-level limits didn't apply, fall back to the previous rules
        if (! $dueDate) {
            $category = strtolower($categoryName);
            $nonFictionCategories = ['filipiniana','circulation','reference','research','thesis'];

            if ($category === 'fiction' && $borrowerType === 'student') {
                $dueDate = Carbon::now()->addMonth()->toDateString();
            } elseif (in_array($category, $nonFictionCategories)) {
                $dueDate = Carbon::now()->addDays(7)->toDateString();
            } else {
                if ($borrowerType === 'student') {
                    $dueDate = Carbon::now()->addDays(7)->toDateString();
                } else {
                    $dueDate = Carbon::now()->addDays(30)->toDateString();
                }
            }
        }

        $bor = Borrowing::create([
            'collection_id' => $collection->id,
            'borrower_type' => $data['borrower_type'],
            'borrower_id' => $data['borrower_id'],
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'status' => 'Borrowed',
            'processed_by' => Auth::id(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        Log::info('Borrowing created', ['borrowing_id' => $bor->id, 'collection_id' => $collection->id, 'borrower_id' => $bor->borrower_id]);

        // Update collection status
        $collection->status = 'Borrowed';
        $collection->save();

        Log::info('Collection status updated to Borrowed', ['collection_id' => $collection->id]);
        return Redirect::route('library.borrowings.index')->with('success', 'Borrowing processed.');
        } catch (\Throwable $e) {
            Log::error('Error processing borrowing', ['exception' => $e, 'payload' => $request->all()]);
            return Redirect::route('library.borrowings.index')->with('error', 'Error processing borrowing: '.$e->getMessage());
        }
    }

    public function processReturn(Request $request, $id)
    {
        if (! $this->checkAccess()) {
            return Redirect::route('library.borrowings.index')->with('error', 'Unauthorized');
        }

        $borrowing = Borrowing::findOrFail($id);
        if ($borrowing->return_date) {
            return Redirect::route('library.borrowings.index')->with('error', 'Borrowing already returned.');
        }

        $borrowing->return_date = Carbon::now()->toDateString();
        $borrowing->status = 'Returned';
        $borrowing->save();

        // Update collection status back to Available
        $collection = $borrowing->collection;
        if ($collection) {
            $collection->status = 'Available';
            $collection->save();
        }

        return Redirect::route('library.borrowings.index')->with('success', 'Return processed.');
    }

    /**
     * Return borrowing history for a given collection as JSON.
     */
    public function collectionHistory($collectionId)
    {
        $borrowings = Borrowing::with('collection')->where('collection_id', $collectionId)->orderBy('borrow_date', 'desc')->get();

        $borrowings->transform(function ($b) {
            // Resolve borrower name similar to index
            $b->borrower_name = null;
            try {
                $type = strtolower($b->borrower_type ?? '');
                if ($type === 'student') {
                    $student = DB::table('students')->where('id', $b->borrower_id)->first();
                    if ($student) {
                        $first = null; $last = null;
                        foreach (['first_name','firstname','fname','given_name'] as $c) if (isset($student->$c)) { $first = $student->$c; break; }
                        foreach (['last_name','lastname','lname','surname'] as $c) if (isset($student->$c)) { $last = $student->$c; break; }
                        if ($first || $last) $b->borrower_name = trim(($last ? $last.' ' : '').($first ?? ''));
                        else $b->borrower_name = trim(implode(' ', array_filter((array)$student)));
                    }
                } elseif ($type === 'employee') {
                    $emp = DB::table('users')->where('id', $b->borrower_id)->first();
                    if ($emp) {
                        if (isset($emp->name) && $emp->name) {
                            $b->borrower_name = $emp->name;
                        } else {
                            $first = null; $last = null;
                            foreach (['first_name','firstname','fname','given_name'] as $c) if (isset($emp->$c)) { $first = $emp->$c; break; }
                            foreach (['last_name','lastname','lname','surname'] as $c) if (isset($emp->$c)) { $last = $emp->$c; break; }
                            if ($first || $last) $b->borrower_name = trim(($last ? $last.' ' : '').($first ?? ''));
                            else $b->borrower_name = trim(implode(' ', array_filter((array)$emp)));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
            if (! $b->borrower_name) $b->borrower_name = ($b->borrower_type ?? 'Unknown').' #'.$b->borrower_id;
            return $b;
        });

        return response()->json($borrowings);
    }

    /**
     * Return borrowing history for a given borrower (type + id) as JSON.
     */
    public function borrowerHistory($borrowerType, $borrowerId)
    {
        $borrowings = Borrowing::with('collection')
            ->where('borrower_type', $borrowerType)
            ->where('borrower_id', $borrowerId)
            ->orderBy('borrow_date', 'desc')
            ->get();

        // enrich collection title and keep borrower name resolution simple (borrower is known)
        $borrowings->transform(function ($b) {
            $b->collection_title = $b->collection->title ?? null;
            return $b;
        });

        return response()->json($borrowings);
    }

    public function overrideDueDate(Request $request, $id)
    {
        if (! $this->checkAccess()) {
            return Redirect::route('library.borrowings.index')->with('error', 'Unauthorized');
        }

        $borrowing = Borrowing::findOrFail($id);
        $data = $request->validate([
            'due_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        $borrowing->due_date = $data['due_date'];
        if (isset($data['remarks'])) $borrowing->remarks = $data['remarks'];
        $borrowing->save();

        return Redirect::route('library.borrowings.index')->with('success', 'Due date overridden.');
    }
}
