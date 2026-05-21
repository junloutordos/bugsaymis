<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load(['roles']);

        // Resolve by FK to avoid naming conflict — users table has both
        // an 'office' string column and an office() relationship.
        $division = $user->division_id ? Division::select('id', 'division_name as name')->find($user->division_id) : null;
        $office   = $user->office_id   ? Office::select('id', 'name')->find($user->office_id)     : null;

        return Inertia::render('Profile/Index', [
            'profile' => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'position'        => $user->position,
                'specialization'  => $user->specialization,
                'sex'             => $user->sex,
                'emp_category'    => $user->emp_category,
                'employee_no'     => $user->employee_no,
                'salary_grade'    => $user->salary_grade,
                'salary_step'     => $user->salary_step,
                'status'          => $user->status,
                'division'        => $division ? $division->only('id', 'name') : null,
                'office'          => $office   ? $office->only('id', 'name')   : null,
                'roles'           => $user->roles->pluck('name'),
                'profile_picture' => $user->profile_picture,
                'has_signature'   => (bool) $user->electronic_signature,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'specialization'      => 'nullable|string|max:255',
            'profile_photo_base64'=> 'nullable|string',
            'profile_photo_mime'  => 'nullable|string|in:image/jpeg,image/jpg,image/png',
        ]);

        $user = $request->user();
        $user->name            = $validated['name'];
        $user->specialization  = $validated['specialization'] ?? $user->specialization;

        if (! empty($validated['profile_photo_base64'])) {
            $raw  = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $validated['profile_photo_base64']));
            $ext  = str_contains($validated['profile_photo_mime'] ?? '', 'png') ? 'png' : 'jpg';
            $path = 'profile_pictures/' . $user->id . '_' . time() . '.' . $ext;

            Storage::disk('s3')->put($path, $raw);

            // Delete old photo from S3
            if ($user->profile_picture && $user->profile_picture !== $path) {
                Storage::disk('s3')->delete($user->profile_picture);
            }

            $user->profile_picture = $path;
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}
