<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\NotificationSetting;
use App\Notifications\NewEpisodeNotification;
use App\Services\WebPushService;
use App\Support\SeriesUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchEpisodeNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId)
    {
    }

    public function handle(): void
    {
        $event = NotificationEvent::query()
            ->with(['series'])
            ->find($this->eventId);

        if (!$event || !$event->series) {
            return;
        }

        $settings = NotificationSetting::query()
            ->with('user')
            ->where('series_id', $event->series_id)
            ->where('is_enabled', true)
            ->get();

        foreach ($settings as $setting) {
            $user = $setting->user;
            if (!$user) {
                continue;
            }

            if (!self::matchesSetting($setting, $event)) {
                continue;
            }

            $sendEmail = (bool) $user->notify_via_email && (bool) $user->email;
            $sendSite = (bool) $user->notify_via_site;
            $sendPush = (bool) $user->notify_via_push;

            if (!$sendEmail && !$sendSite && !$sendPush) {
                continue;
            }

            $delivery = null;
            $claimed = true;

            if ($sendEmail || $sendSite) {
                $delivery = NotificationDelivery::query()->firstOrCreate(
                    [
                        'notification_event_id' => $event->id,
                        'user_id' => $user->id,
                    ],
                    ['status' => 'queued']
                );

                // Atomically claim the delivery so concurrent workers do not double-send email.
                $claimed = NotificationDelivery::query()
                    ->whereKey($delivery->id)
                    ->whereIn('status', ['queued', 'failed'])
                    ->update(['status' => 'sending', 'error' => null]) > 0;
            }

            if (!$claimed) {
                continue;
            }

            $failed = false;
            $error = null;

            if ($sendEmail) {
                try {
                    $user->notify(new NewEpisodeNotification($event));
                } catch (Throwable $e) {
                    $failed = true;
                    $error = $e->getMessage();
                    Log::error('Episode notification failed', [
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($sendPush) {
                self::sendPush($user, $event);
            }

            if ($delivery) {
                if ($failed && !$sendSite) {
                    $delivery->update([
                        'status' => 'failed',
                        'error' => $error,
                    ]);
                } else {
                    $delivery->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error' => $failed ? $error : null,
                    ]);
                }
            }
        }
    }

    private static function sendPush($user, NotificationEvent $event): void
    {
        try {
            $series = $event->series;
            $episodeLabel = '';
            if ($event->season_number && $event->episode_number) {
                $episodeLabel = sprintf(
                    '%d сезон, %d серия',
                    $event->season_number,
                    $event->episode_number
                );
            }

            WebPushService::sendToUser($user, [
                'title' => 'Новая серия — ' . ($series->title ?? 'сериал'),
                'body' => $episodeLabel !== ''
                    ? $episodeLabel . ($event->voice ? ' · ' . $event->voice : '')
                    : 'Вышла новая серия',
                'url' => url(SeriesUrl::path($series)),
                'icon' => $series->poster_url ?: null,
                'tag' => 'series-' . $event->series_id . '-ep-' . $event->id,
            ]);
        } catch (Throwable $e) {
            Log::error('Episode web push failed', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function matchesSetting(NotificationSetting $setting, NotificationEvent $event): bool
    {
        if ($setting->notify_any) {
            return true;
        }

        // Progress-based events have no voice — notify all series subscribers.
        if ($event->voice === null || $event->voice === '') {
            return true;
        }

        $voices = $setting->voices ?? [];
        if ($voices === []) {
            return false;
        }

        return in_array($event->voice, $voices, true);
    }
}
