<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\LibraryAttendance;

class LibraryAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 20;
        $query = LibraryAttendance::query()->orderBy('scanned_at', 'desc');

        if ($q = $request->input('q')) {
            $query->where(function($qr) use ($q) {
                $qr->where('pisay_systemid', 'like', "%$q%")
                   ->orWhere('student_name', 'like', "%$q%");
            });
        }

        $attendances = $query->paginate($perPage)->appends($request->only('q'));

        return Inertia::render('Library/Attendance', [
            'attendances' => $attendances,
            'q' => $request->input('q'),
        ]);
    }
}
