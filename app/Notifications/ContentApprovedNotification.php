<?php

namespace App\Notifications;

use App\Models\NotificationEvent;
use App\Support\SeriesUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContentApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public NotificationEvent $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $series = $this->event->series;
        $payload = is_array($this->event->payload) ? $this->event->payload : [];
        $title = (string) ($payload['title'] ?? 'Контент одобрен');
        $preview = trim((string) ($payload['preview'] ?? ''));
        $anchor = trim((string) ($payload['anchor'] ?? ''));
        $url = url(SeriesUrl::path($series));
        if ($anchor !== '') {
            $url .= '#' . ltrim($anchor, '#');
        }

        $mail = (new MailMessage())
            ->subject($title . ' — ' . ($series->title ?? 'сериал'))
            ->greeting('Здравствуйте!')
            ->line($title . '.');

        if ($series?->title) {
            $mail->line('Сериал: «' . $series->title . '».');
        }

        if ($preview !== '') {
            $mail->line('«' . $preview . '»');
        }

        return $mail
            ->action('Открыть на сайте', $url)
            ->line('Вы получили это письмо, потому что оставили комментарий или рецензию на сайте.');
    }
}
