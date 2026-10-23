<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffLoginCode extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode masuk dashboard Dthree')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Gunakan kode berikut untuk menyelesaikan proses masuk ke dashboard Dthree:')
            ->line($this->code)
            ->line('Kode berlaku selama 10 menit. Abaikan email ini jika Anda tidak mencoba masuk.');
    }
}
