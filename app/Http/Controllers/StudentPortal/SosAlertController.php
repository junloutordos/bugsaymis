<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\Request;

class SosAlertController extends Controller
{
    public function trigger(Request $request, SosAlertService $service)
    {
        $validated = $request->validate([
            'alert_type' => 'required|in:medical,security,fire_disaster,general',
            'is_silent'  => 'boolean',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'accuracy'   => 'nullable|numeric',
        ]);

        $student = Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();

        $result = $service->trigger(
            triggerable: $student,
            alertType:   $validated['alert_type'],
            isSilent:    $validated['is_silent'] ?? false,
            lat:         $validated['lat'] ?? null,
            lng:         $validated['lng'] ?? null,
            accuracy:    $validated['accuracy'] ?? null,
            ip:          $request->ip(),
        );

        if ($result['blocked']) {
            return response()->json([
                'blocked'           => true,
                'message'           => config('sos.off_campus_message'),
                'emergency_hotline' => config('sos.emergency_hotline_number'),
            ], 422);
        }

        return response()->json(['blocked' => false, 'alert_id' => $result['alert']->id], 201);
    }
}
