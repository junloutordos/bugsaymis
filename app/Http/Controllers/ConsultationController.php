<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\ConsultationCreatedMail;
use App\Mail\ConsultationScheduledMail;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';

        // Nurses and admins see all; others only their own
        $consultations = ($role === 'Administrator' || $role === 'Nurse')
            ? Consultation::latest()->get()
            : Consultation::where('email', $user->email)->latest()->get();

        return Inertia::render('Health/Consultations/Index', [
            'consultations' => $consultations,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
            'contact' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $data = $request->only(['reason','contact']);
        if ($user) {
            $data['requestor'] = $user->name;
            $data['email'] = $user->email;
            $data['unit'] = $user->division->division_name ?? $user->office ?? null;
        }

        $consult = Consultation::create($data + ['status' => 'Pending']);

        // Notify nurses
        try {
            $nurses = User::whereHas('role', function($q){ $q->where('name','Nurse'); })->get();
            foreach ($nurses as $n) {
                if ($n->email) {
                    Mail::to($n->email)->send(new ConsultationCreatedMail($consult));
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify nurses: '.$e->getMessage());
        }

        return redirect()->route('consultations.index')->with('success','Consultation requested');
    }

    public function update(Request $request, Consultation $consultation)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';

        // Only Nurse or Admin can schedule
        if (! in_array($role, ['Administrator','Nurse'])) {
            abort(403);
        }

        $request->validate([
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $consultation->scheduled_at = $request->input('scheduled_at');
        $consultation->nurse_id = $user->id;
        $consultation->notes = $request->input('notes');
        $consultation->status = 'Scheduled';
        $consultation->save();

        // Notify requester
        try {
            Mail::to($consultation->email)->send(new ConsultationScheduledMail($consultation));
        } catch (\Throwable $e) {
            logger()->error('Failed to send consultation scheduled email: '.$e->getMessage());
        }

        return redirect()->route('consultations.index')->with('success','Consultation scheduled');
    }
}
