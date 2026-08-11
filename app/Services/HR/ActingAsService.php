<?php

namespace App\Services\HR;

use App\Models\HR\ActingAsSession;
use App\Models\HR\Substitution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ActingAsService
{
    private const SESSION_TRUE_USER_ID = 'true_user_id';
    private const SESSION_SUBSTITUTION_ID = 'acting_substitution_id';

    public function start(Substitution $substitution, User $trueUser, Request $request): void
    {
        if ((int) $substitution->substitute_user_id !== (int) $trueUser->id) {
            throw ValidationException::withMessages(['substitution' => ['You are not the substitute for this grant.']]);
        }

        if (! $substitution->isWithinWindow()) {
            throw ValidationException::withMessages(['substitution' => ['This substitution is not currently within its active date window.']]);
        }

        // Only one active identity at a time — auto-exit any other live session first.
        if ($request->session()->has(self::SESSION_SUBSTITUTION_ID)) {
            $this->exit($request, 'manual');
        }

        ActingAsSession::create([
            'substitution_id' => $substitution->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        $request->session()->put(self::SESSION_TRUE_USER_ID, $trueUser->id);
        $request->session()->put(self::SESSION_SUBSTITUTION_ID, $substitution->id);

        Auth::login($substitution->originalUser);
    }

    public function exit(Request $request, string $reason = 'manual'): void
    {
        $substitutionId = $request->session()->get(self::SESSION_SUBSTITUTION_ID);
        $trueUserId = $request->session()->get(self::SESSION_TRUE_USER_ID);

        if (! $substitutionId || ! $trueUserId) {
            return;
        }

        ActingAsSession::where('substitution_id', $substitutionId)
            ->open()
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);

        $request->session()->forget([self::SESSION_TRUE_USER_ID, self::SESSION_SUBSTITUTION_ID]);

        $trueUser = User::find($trueUserId);
        if ($trueUser) {
            Auth::login($trueUser);
        }
    }

    public function forceEndForSubstitution(Substitution $substitution, string $reason): void
    {
        ActingAsSession::where('substitution_id', $substitution->id)
            ->open()
            ->update(['ended_at' => now(), 'ended_reason' => $reason]);
    }

    /** The substitution currently being acted-as in this request's session, if any and still valid. */
    public function currentSubstitution(Request $request): ?Substitution
    {
        $substitutionId = $request->session()->get(self::SESSION_SUBSTITUTION_ID);
        if (! $substitutionId) {
            return null;
        }

        return Substitution::find($substitutionId);
    }
}
