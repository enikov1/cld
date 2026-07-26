<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    /**
     * @return array{publicKey: string, privateKey: string, subject: string}|null
     */
    public static function vapidKeys(): ?array
    {
        $public = trim((string) config('webpush.vapid.public_key', ''));
        $private = trim((string) config('webpush.vapid.private_key', ''));
        $subject = trim((string) config('webpush.vapid.subject', ''));

        if ($public !== '' && $private !== '') {
            return [
                'publicKey' => $public,
                'privateKey' => $private,
                'subject' => self::normalizeSubject($subject),
            ];
        }

        $path = storage_path('app/vapid.json');
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && !empty($decoded['publicKey']) && !empty($decoded['privateKey'])) {
                return [
                    'publicKey' => (string) $decoded['publicKey'],
                    'privateKey' => (string) $decoded['privateKey'],
                    'subject' => self::normalizeSubject($subject !== '' ? $subject : (string) ($decoded['subject'] ?? '')),
                ];
            }
        }

        try {
            $generated = VAPID::createVapidKeys();
        } catch (Throwable $e) {
            Log::error('Failed to generate VAPID keys', ['error' => $e->getMessage()]);

            return null;
        }

        $payload = [
            'publicKey' => $generated['publicKey'],
            'privateKey' => $generated['privateKey'],
            'subject' => self::normalizeSubject($subject),
        ];

        try {
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            Log::warning('Could not persist VAPID keys', ['error' => $e->getMessage()]);
        }

        return $payload;
    }

    public static function publicKey(): ?string
    {
        return self::vapidKeys()['publicKey'] ?? null;
    }

    public static function subscribe(User $user, array $subscription, ?string $userAgent = null): PushSubscription
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        $keys = $subscription['keys'] ?? [];

        return PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
            ],
            [
                'public_key' => (string) ($keys['p256dh'] ?? ''),
                'auth_token' => (string) ($keys['auth'] ?? ''),
                'content_encoding' => (string) ($subscription['contentEncoding'] ?? $subscription['encoding'] ?? 'aesgcm'),
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]
        );
    }

    public static function unsubscribe(User $user, ?string $endpoint = null): void
    {
        $query = PushSubscription::query()->where('user_id', $user->id);
        if ($endpoint) {
            $query->where('endpoint', $endpoint);
        }
        $query->delete();
    }

    /**
     * @param array{title: string, body?: string, url?: string, icon?: string, tag?: string} $payload
     */
    public static function sendToUser(User $user, array $payload): void
    {
        if (!(bool) $user->notify_via_push) {
            return;
        }

        $keys = self::vapidKeys();
        if (!$keys) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $user->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $keys['subject'],
                    'publicKey' => $keys['publicKey'],
                    'privateKey' => $keys['privateKey'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('WebPush init failed', ['error' => $e->getMessage()]);

            return;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        foreach ($subscriptions as $row) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $row->endpoint,
                    'publicKey' => $row->public_key,
                    'authToken' => $row->auth_token,
                    'contentEncoding' => $row->content_encoding ?: 'aesgcm',
                ]);
                $webPush->queueNotification($subscription, $json);
            } catch (Throwable $e) {
                Log::warning('Invalid push subscription', [
                    'user_id' => $user->id,
                    'subscription_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
                $row->delete();
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $endpoint = $report->getEndpoint();
                PushSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('endpoint', $endpoint)
                    ->delete();
            } else {
                Log::warning('Web push failed', [
                    'user_id' => $user->id,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }

    private static function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return 'mailto:noreply@localhost';
        }
        if (str_starts_with($subject, 'mailto:') || str_starts_with($subject, 'https://') || str_starts_with($subject, 'http://')) {
            return $subject;
        }

        return 'mailto:' . $subject;
    }
}
