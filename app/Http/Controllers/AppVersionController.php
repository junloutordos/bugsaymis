<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppVersionController extends Controller
{
    /**
     * Versions are created automatically by CI (app:version-sync reading the
     * baked-in version.json) — admins can only polish the generated text.
     */
    public function update(Request $request, AppVersion $appVersion)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'remarks'                => 'required|string|max:500',
            'changes'                => 'nullable|array',
            'changes.features'       => 'nullable|array',
            'changes.features.*'     => 'string|max:300',
            'changes.fixes'          => 'nullable|array',
            'changes.fixes.*'        => 'string|max:300',
            'changes.improvements'   => 'nullable|array',
            'changes.improvements.*' => 'string|max:300',
        ]);

        $appVersion->update([
            'remarks' => $validated['remarks'],
            'changes' => $validated['changes'] ?? null,
        ]);

        Cache::forget('app.version');

        return back()->with('success', 'Version ' . $appVersion->version . ' updated.');
    }
}
