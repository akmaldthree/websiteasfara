<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPayout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries()
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
