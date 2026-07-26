<?php

namespace App\Http\Controllers;

use App\Services\WebPushService;
use App\Support\SiteConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey()
    {
        if (!SiteConfig::bool('notifications_enabled')) {
            return response()->json(['publicKey' => null], 404);
        }

        return response()->json([
            'publicKey' => WebPushService::publicKey(),
        ]);
    }

    public function store(Request $request)
    {
        if (!SiteConfig::bool('notifications_enabled')) {
            return response()->json(['ok' => false], 403);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Требуется авторизация'], 401);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        WebPushService::subscribe($user, $data, $request->userAgent());

        if (!$user->notify_via_push) {
            $user->forceFill(['notify_via_push' => true])->save();
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->validate([
            'endpoint' => ['nullable', 'string', 'max:500'],
        ]);

        WebPushService::unsubscribe($user, $data['endpoint'] ?? null);

        return response()->json(['ok' => true]);
    }
}
