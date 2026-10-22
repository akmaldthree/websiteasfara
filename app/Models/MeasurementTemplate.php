<?php

namespace App\Models;

use Database\Factories\MeasurementTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeasurementTemplate extends Model
{
    /** @use HasFactory<MeasurementTemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'surcharge' => 'integer', 'active' => 'boolean'];
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }
}
