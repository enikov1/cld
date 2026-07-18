<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\NotificationSetting;
use App\Notifications\NewEpisodeNotification;
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

            if (!$sendEmail && !$sendSite) {
                continue;
            }

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
                ->update(['status' => 'sending', 'error' => null]);

            if ($claimed === 0) {
                continue;
            }

            if ($sendEmail) {
                try {
                    $user->notify(new NewEpisodeNotification($event));
                    $delivery->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error' => null,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Episode notification failed', [
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    $delivery->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($sendSite) {
                $delivery->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
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
