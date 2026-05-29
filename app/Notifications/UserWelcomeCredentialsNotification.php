<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserWelcomeCredentialsNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Bienvenido(a) a Gestión de Proyectos')
            ->view('emails.user-welcome', [
                'name' => (string) ($notifiable->name ?? ''),
                'email' => (string) ($notifiable->email ?? '-'),
                'loginUrl' => url('/panel/login'),
                'logoUrl' => url('/img/logo.jpg'),
            ]);
    }
}
