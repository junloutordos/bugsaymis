<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Substitution;
use App\Services\HR\ActingAsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActingAsController extends Controller
{
    public function __construct(private ActingAsService $actingAs) {}

    public function start(Request $request, Substitution $substitution)
    {
        $this->actingAs->start($substitution, Auth::user(), $request);

        return redirect()->route('dashboard')->with('success', "Now acting as {$substitution->originalUser->name}.");
    }

    public function exit(Request $request)
    {
        $this->actingAs->exit($request, 'manual');

        return redirect()->route('dashboard')->with('success', 'Returned to your own account.');
    }
}
