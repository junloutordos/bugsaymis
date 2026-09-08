<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rewards\GantimpalaNominationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Public, unauthenticated kiosk for external parties to submit a Gantimpala
 * Agad Award nomination (PRAISE Form 4). No login required — reachable from
 * a browser running in kiosk/fullscreen mode on a lobby tablet/PC.
 *
 * Abuse mitigation: throttled route (see routes/web.php), a honeypot field,
 * and a minimum-fill-time check before accepting a submission.
 */
class GantimpalaKioskController extends Controller
{
    private const MIN_FILL_SECONDS = 4;

    public function index()
    {
        return Inertia::render('Public/GantimpalaKiosk', [
            'formToken' => encrypt(now()->timestamp),
        ]);
    }

    public function searchEmployees(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['employees' => []]);
        }

        $employees = User::employees()
            ->where('status', '<>', 'inactive')
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json(['employees' => $employees]);
    }

    public function store(Request $request, GantimpalaNominationController $nominations)
    {
        // Honeypot — a hidden field real users never fill; bots often do.
        if ($request->filled('website')) {
            Log::warning('Gantimpala kiosk honeypot triggered', ['ip' => $request->ip()]);
            throw ValidationException::withMessages(['activity_conducted' => 'Submission could not be processed.']);
        }

        // Minimum fill-time check using the encrypted form token issued on page load.
        try {
            $issuedAt = (int) decrypt($request->input('form_token'));
            if ((now()->timestamp - $issuedAt) < self::MIN_FILL_SECONDS) {
                throw ValidationException::withMessages(['activity_conducted' => 'Please take a moment to review the form before submitting.']);
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw ValidationException::withMessages(['activity_conducted' => 'Your session expired. Please reload the kiosk page.']);
        }

        $data = $nominations->validateNomination($request);

        $nomination = $nominations->persist($data, 'kiosk', $request);

        return response()->json([
            'ok'           => true,
            'reference_no' => $nomination->reference_no,
        ]);
    }
}
