<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'active' => 'boolean', 'combinable' => 'boolean'];
    }

    public function isAvailableAt(CarbonInterface $time): bool
    {
        return $this->active && $time->between($this->starts_at, $this->ends_at) && $this->used < $this->usage_limit;
    }

    public function appliesToAudience(bool $signedIn, ?int $groupId): bool
    {
        $matchesSegment = match ($this->segment) {
            'all' => true,
            'guest' => ! $signedIn,
            'customer' => $signedIn && $groupId === null,
            'business' => $groupId !== null,
            default => false,
        };

        return $matchesSegment && (! $this->price_group_id || $groupId === $this->price_group_id);
    }
}
