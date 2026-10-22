<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleComponent extends Model
{
    protected $guarded = [];

    public function bundleVariant()
    {
        return $this->belongsTo(Variant::class, 'bundle_variant_id');
    }

    public function componentVariant()
    {
        return $this->belongsTo(Variant::class, 'component_variant_id');
    }
}
