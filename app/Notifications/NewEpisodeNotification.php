<?php

namespace App\Notifications;

use App\Models\NotificationEvent;
use App\Support\SeriesUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewEpisodeNotification extends Notification
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
        $url = url(SeriesUrl::path($series));

        $episodeLabel = '';
        if ($this->event->season_number && $this->event->episode_number) {
            $episodeLabel = sprintf(
                '%d сезон, %d серия',
                $this->event->season_number,
                $this->event->episode_number
            );
        }

        $mail = (new MailMessage())
            ->subject('Новая серия — ' . $series->title)
            ->greeting('Здравствуйте!')
            ->line('Вышла новая серия сериала «' . $series->title . '».');

        if ($episodeLabel !== '') {
            $mail->line($episodeLabel);
        }

        if ($this->event->voice) {
            $mail->line('Озвучка: ' . $this->event->voice);
        }

        return $mail
            ->action('Смотреть на сайте', $url)
            ->line('Вы получили это письмо, потому что подписаны на уведомления о новых сериях.');
    }
}
