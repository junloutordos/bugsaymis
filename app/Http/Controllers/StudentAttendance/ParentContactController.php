<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\ParentContact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParentContactController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('students.attendance.view');

        $contacts = ParentContact::with('students')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return inertia('StudentAttendance/Parents/Index', [
            'contacts' => $contacts,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('students.attendance.view');

        $validated = $this->validateContact($request);

        ParentContact::create($validated);

        return back()->with('success', 'Parent contact added.');
    }

    public function update(Request $request, ParentContact $parentContact)
    {
        $this->authorize('students.attendance.view');

        $validated = $this->validateContact($request, $parentContact->id);

        $parentContact->update($validated);

        return back()->with('success', 'Parent contact updated.');
    }

    public function destroy(ParentContact $parentContact)
    {
        $this->authorize('students.attendance.view');

        $parentContact->delete();

        return back()->with('success', 'Parent contact removed.');
    }

    private function validateContact(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:200'],
            'email'            => ['nullable', 'email', 'max:255'],
            'mobile_phone'     => ['nullable', 'string', 'max:20'],
            'fcm_device_token' => ['nullable', 'string', 'max:500'],
            'notify_email'     => ['boolean'],
            'notify_push'      => ['boolean'],
            'notify_sms'       => ['boolean'],
        ]);
    }
}
