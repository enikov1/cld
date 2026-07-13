<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class UserResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        $email = urlencode($notifiable->getEmailForPasswordReset());

        return url('/?auth=reset&token=' . $this->token . '&email=' . $email);
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage())
            ->subject('Сброс пароля — ' . config('app.name'))
            ->line('Вы получили это письмо, потому что был запрошен сброс пароля для вашего аккаунта.')
            ->action('Сбросить пароль', $url)
            ->line('Если вы не запрашивали сброс, просто проигнорируйте это письмо.');
    }
}
