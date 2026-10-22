<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function send(int $id): bool
    {
        if (! config('commerce.email_notifications')) {
            return false;
        }
        $delivery = DB::transaction(function () use ($id) {
            $row = NotificationDelivery::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($row->channel !== 'email' || ! in_array($row->status, ['pending', 'failed']) ||
                $row->attempts >= 5 || $row->available_at?->isFuture()) {
                return null;
            }
            $row->update(['status' => 'sending', 'attempts' => $row->attempts + 1, 'claimed_at' => now(), 'last_error' => null]);

            return $row;
        });
        if (! $delivery) {
            return false;
        }
        try {
            if (in_array(config('mail.default'), ['log', 'array'])) {
                throw new \RuntimeException('Email transport has not been configured.');
            }
            Mail::raw($delivery->payload['body'], function (Message $message) use ($delivery) {
                $message->to($delivery->recipient)->subject($delivery->payload['subject']);
                $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $delivery->dedupe_key.'@dthree.local');
            });
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'claimed_at' => null]);

            return true;
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => 'failed', 'claimed_at' => null, 'available_at' => now()->addMinutes(2 ** $delivery->attempts),
                'last_error' => 'Pengiriman gagal. Periksa konfigurasi dan log penyedia email. Percobaan '.$delivery->attempts.'/5.',
            ]);
            // SMTP responses may contain credentials or recipient data.
            logger()->warning('transaction_email_failed', ['delivery_id' => $delivery->id, 'exception_type' => get_class($exception)]);

            return false;
        }
    }

    public function recoverStale(): int
    {
        return NotificationDelivery::where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(10))
            ->update(['status' => 'uncertain', 'last_error' => 'Worker terhenti. Periksa penerimaan pada penyedia sebelum mencoba ulang.']);
    }

    public function retry(NotificationDelivery $delivery, bool $confirmed): void
    {
        DB::transaction(function () use ($delivery, $confirmed) {
            $row = NotificationDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($row->channel !== 'email' || ! in_array($row->status, ['failed', 'uncertain']) || ! $confirmed) {
                throw ValidationException::withMessages(['notification' => 'Periksa status penyedia dan konfirmasi sebelum mencoba ulang.']);
            }
            $row->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'claimed_at' => null, 'last_error' => null]);
            Audit::record('notification.retry', 'notification:'.$row->id);
        });
    }
}
