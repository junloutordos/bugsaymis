<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\StudentLinkRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LinkConfirmController extends Controller
{
    /** GET /student-attendance/link/{token} */
    public function show(string $token): View
    {
        $req = StudentLinkRequest::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $req) {
            return view('student_attendance.link_confirm', [
                'state'       => 'invalid',
                'parentName'  => null,
                'relationship'=> null,
                'token'       => null,
            ]);
        }

        return view('student_attendance.link_confirm', [
            'state'        => 'pending',
            'parentName'   => $req->parentContact->name,
            'relationship' => ucfirst($req->relationship),
            'token'        => $token,
        ]);
    }

    /** POST /student-attendance/link/{token}/confirm */
    public function confirm(Request $request, string $token): View
    {
        $req = StudentLinkRequest::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $req) {
            return view('student_attendance.link_confirm', [
                'state' => 'invalid', 'parentName' => null, 'relationship' => null, 'token' => null,
            ]);
        }

        // Attach to pivot (skip duplicates)
        $req->parentContact->students()->syncWithoutDetaching([
            $req->student_id => ['relationship' => $req->relationship],
        ]);

        $req->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        return view('student_attendance.link_confirm', [
            'state'       => 'confirmed',
            'parentName'  => $req->parentContact->name,
            'relationship'=> ucfirst($req->relationship),
            'token'       => null,
        ]);
    }

    /** POST /student-attendance/link/{token}/deny */
    public function deny(string $token): View
    {
        $req = StudentLinkRequest::where('token', $token)->where('status', 'pending')->first();

        if ($req) {
            $req->update(['status' => 'denied']);
        }

        return view('student_attendance.link_confirm', [
            'state'       => 'denied',
            'parentName'  => $req?->parentContact?->name,
            'relationship'=> null,
            'token'       => null,
        ]);
    }
}
