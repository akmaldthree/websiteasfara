<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'size' => 'integer'];
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
