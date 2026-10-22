<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
