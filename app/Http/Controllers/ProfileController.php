<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request)
    {
        Log::info('ProfileController::update called', ['has_files' => count($request->files->all()), 'files_keys' => array_keys($request->files->all())]);
        Log::info('Request input keys', ['keys' => array_keys($request->all())]);
        $user = $request->user();
        $data = $request->validated();

        // Store uploads on the private S3 disk — served through the storage proxy.
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            Log::info('Profile picture received', ['original' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType()]);
            $path = \Illuminate\Support\Facades\Storage::disk('s3')->putFile('profile_pictures', $file);
            if (! $path) {
                return back()->withErrors(['profile_picture' => 'Failed to upload profile picture. Please try again.']);
            }
            $data['profile_picture'] = $path;
        } else {
            Log::info('No profile picture file received.');
        }

        if ($request->hasFile('electronic_signature')) {
            $file = $request->file('electronic_signature');
            Log::info('Electronic signature received', ['original' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType()]);
            $path = \Illuminate\Support\Facades\Storage::disk('s3')->putFile('signatures', $file);
            if (! $path) {
                return back()->withErrors(['electronic_signature' => 'Failed to upload signature. Please try again.']);
            }
            Log::info('Electronic signature stored', ['path' => $path]);
            $data['electronic_signature'] = $path;
        } else {
            Log::info('No electronic signature file received.');
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // If this is a normal AJAX request (not an Inertia request), return JSON so the client can handle it.
        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Profile updated']);
        }

        // Otherwise return a RedirectResponse (Inertia or normal form submit will follow it).
        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
