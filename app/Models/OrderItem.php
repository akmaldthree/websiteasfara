<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'custom_cost' => 'integer'];
    }

    public function productionMilestones()
    {
        return $this->hasMany(ProductionMilestone::class)->orderBy('id');
    }

    public function components()
    {
        return $this->hasMany(OrderItemComponent::class)->orderBy('id');
    }
}
