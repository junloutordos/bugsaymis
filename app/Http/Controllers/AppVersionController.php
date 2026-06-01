<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppVersionController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'version'    => 'required|string|max:30|unique:app_versions,version',
            'date'       => 'required|date',
            'remarks'    => 'required|string',
            'is_current' => 'boolean',
        ]);

        DB::transaction(function () use ($validated) {
            if (!empty($validated['is_current'])) {
                AppVersion::query()->update(['is_current' => false]);
            }
            AppVersion::create($validated);
        });

        return back()->with('success', 'Version ' . $validated['version'] . ' added successfully.');
    }
}
