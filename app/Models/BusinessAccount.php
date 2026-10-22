<?php

namespace App\Models;

use Database\Factories\BusinessAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessAccount extends Model
{
    /** @use HasFactory<BusinessAccountFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function priceGroup()
    {
        return $this->belongsTo(PriceGroup::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
