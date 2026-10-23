<?php

namespace App\Notifications;

use App\Models\ProductQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductQuestionAnswered extends Notification
{
    use Queueable;

    public function __construct(public ProductQuestion $question) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pertanyaan Anda sudah dijawab · Dthree')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Tim Dthree sudah menjawab pertanyaan Anda tentang '.$this->question->series->name.'.')
            ->line('Jawaban: '.$this->question->answer)
            ->action('Lihat produk', route('product', $this->question->series->slug));
    }
}
