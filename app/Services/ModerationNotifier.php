<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ContentApprovedNotification;
use App\Support\CommentBody;
use App\Support\SeriesUrl;
use App\Support\SiteConfig;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModerationNotifier
{
    public static function commentApproved(Comment $comment, string $previousStatus): void
    {
        if ($previousStatus === 'approved' || $comment->status !== 'approved') {
            return;
        }

        if (!$comment->user_id) {
            return;
        }

        $comment->loadMissing(['user', 'series']);
        $user = $comment->user;
        $series = $comment->series;
        if (!$user || !$series) {
            return;
        }

        $preview = self::preview($comment->body);
        self::dispatchToUser($user, $series->id, 'comment_approved', [
            'subject_type' => 'comment',
            'subject_id' => $comment->id,
            'title' => SiteConfig::str('notifications_ui_comment_approved'),
            'preview' => $preview,
            'anchor' => 'commentsSection',
        ]);
    }

    public static function reviewApproved(Review $review, string $previousStatus): void
    {
        if ($previousStatus === 'approved' || $review->status !== 'approved') {
            return;
        }

        if (!$review->user_id) {
            return;
        }

        $review->loadMissing(['user', 'series']);
        $user = $review->user;
        $series = $review->series;
        if (!$user || !$series) {
            return;
        }

        $preview = self::preview($review->body);
        self::dispatchToUser($user, $series->id, 'review_approved', [
            'subject_type' => 'review',
            'subject_id' => $review->id,
            'title' => SiteConfig::str('notifications_ui_review_approved'),
            'preview' => $preview,
            'anchor' => 'reviewsSection',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function dispatchToUser(User $user, int $seriesId, string $eventType, array $payload): void
    {
        $event = NotificationEvent::query()->create([
            'series_id' => $seriesId,
            'episode_id' => null,
            'season_number' => null,
            'episode_number' => null,
            'voice' => null,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        $deliver = static function () use ($event, $user): void {
            self::deliver($event->fresh(['series']), $user->fresh());
        };

        if (app()->runningInConsole()) {
            $deliver();

            return;
        }

        dispatch($deliver)->afterResponse();
    }

    private static function deliver(?NotificationEvent $event, ?User $user): void
    {
        if (!$event || !$user || !$event->series) {
            return;
        }

        $sendEmail = (bool) $user->notify_via_email && (bool) $user->email;
        $sendSite = (bool) $user->notify_via_site;
        $sendPush = (bool) $user->notify_via_push;

        if (!$sendEmail && !$sendSite && !$sendPush) {
            return;
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

            $claimed = NotificationDelivery::query()
                ->whereKey($delivery->id)
                ->whereIn('status', ['queued', 'failed'])
                ->update(['status' => 'sending', 'error' => null]) > 0;
        }

        if (!$claimed) {
            return;
        }

        $failed = false;
        $error = null;

        if ($sendEmail) {
            try {
                $user->notify(new ContentApprovedNotification($event));
            } catch (Throwable $e) {
                $failed = true;
                $error = $e->getMessage();
                Log::error('Moderation approval email failed', [
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

    private static function sendPush(User $user, NotificationEvent $event): void
    {
        try {
            $series = $event->series;
            $payload = is_array($event->payload) ? $event->payload : [];
            $title = (string) ($payload['title'] ?? 'Контент одобрен');
            $preview = trim((string) ($payload['preview'] ?? ''));
            $anchor = trim((string) ($payload['anchor'] ?? ''));
            $url = url(SeriesUrl::path($series));
            if ($anchor !== '') {
                $url .= '#' . ltrim($anchor, '#');
            }

            WebPushService::sendToUser($user, [
                'title' => $title,
                'body' => $preview !== ''
                    ? ($series->title . ' — ' . $preview)
                    : ('«' . $series->title . '»'),
                'url' => $url,
                'icon' => $series->poster_url ?: null,
                'tag' => $event->event_type . '-' . $event->id,
            ]);
        } catch (Throwable $e) {
            Log::error('Moderation approval push failed', [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function preview(string $body): string
    {
        $text = CommentBody::effectiveBody($body);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) <= 140) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 137)) . '…';
    }
}
