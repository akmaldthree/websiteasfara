<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipient' => 'encrypted', 'payload' => 'encrypted:array', 'available_at' => 'datetime', 'claimed_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(OrderEvent::class, 'order_event_id');
    }
}
