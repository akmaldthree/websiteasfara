<?php

namespace App\Models;

use Database\Factories\CustomQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomQuote extends Model
{
    /** @use HasFactory<CustomQuoteFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['template_snapshot' => 'array', 'measurements' => 'array', 'surcharge' => 'integer', 'quoted_at' => 'datetime', 'accepted_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function template()
    {
        return $this->belongsTo(MeasurementTemplate::class, 'measurement_template_id');
    }
}
