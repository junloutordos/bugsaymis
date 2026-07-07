<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\LostFound\LostFoundItem;
use App\Services\StudentPortal\LostFoundPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LostFoundController extends Controller
{
    public function __construct(private readonly LostFoundPortalService $svc) {}

    private function student(): object
    {
        $student = DB::table('students')
            ->where('pisaysystemID', session('student_pisaysystemID'))
            ->first();

        abort_unless($student, 403);

        return $student;
    }

    public function show()
    {
        return Inertia::render('StudentPortal/LostFound', $this->svc->screenData($this->student()));
    }

    public function store(Request $request)
    {
        $this->svc->storeReport($request, $this->student());

        return back()->with('success', 'Report submitted.');
    }

    public function photo(LostFoundItem $item)
    {
        return $this->svc->photo($this->student(), $item);
    }
}
