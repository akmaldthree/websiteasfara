<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['policy_snapshot' => 'array', 'closed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CaseItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class)->orderBy('id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
