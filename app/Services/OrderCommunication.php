<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\OrderEvent;

class OrderCommunication
{
    /** Call within the transaction that changes the order. */
    public static function record(Order $order, string $key, string $type, string $message): void
    {
        $event = OrderEvent::firstOrCreate(['event_key' => $order->id.':'.$key], [
            'order_id' => $order->id, 'type' => $type, 'message' => $message,
        ]);
        $channel = $order->email ? 'email' : 'manual';
        NotificationDelivery::firstOrCreate(['dedupe_key' => hash('sha256', $event->id.':'.$channel)], [
            'order_event_id' => $event->id, 'channel' => $channel, 'recipient' => $order->email ?: $order->phone,
            'payload' => [
                'subject' => 'Dthree · '.$order->number,
                'body' => 'Halo '.$order->name.",\n\n".$message."\n\nPesanan: ".$order->number.
                    "\nStatus dan rincian: ".$order->customerUrl()."\n\nSimpan tautan ini secara pribadi.\nDthree",
            ],
            'status' => $channel === 'manual' ? 'manual' : 'pending', 'available_at' => now(),
        ]);
    }
}
