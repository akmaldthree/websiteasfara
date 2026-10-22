<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemComponent extends Model
{
    protected $guarded = [];

    public function item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
