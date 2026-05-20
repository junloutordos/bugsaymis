<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /** POST /api/push-subscriptions — save or update a browser subscription */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint'         => 'required|string|max:500',
            'keys.p256dh'      => 'required|string',
            'keys.auth'        => 'required|string',
            'content_encoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id'          => $request->user()->id,
                'public_key'       => $validated['keys']['p256dh'],
                'auth_token'       => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aesgcm',
            ]
        );

        return response()->json(['ok' => true], 201);
    }

    /** DELETE /api/push-subscriptions — remove subscription (user unsubscribed) */
    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');
        if ($endpoint) {
            PushSubscription::where('endpoint', $endpoint)
                ->where('user_id', $request->user()->id)
                ->delete();
        }
        return response()->json(['ok' => true]);
    }

    /** GET /api/push-subscriptions/vapid-public-key — expose VAPID public key to browser */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('webpush.vapid.public_key', env('VAPID_PUBLIC_KEY')),
        ]);
    }
}
