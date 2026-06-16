<?php

namespace App\Models\ProductMap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductControlVariant extends Model
{
    protected $fillable = [
        'product_control_state_id',
        'source_variant_key',
        'ibs_model',
        'sm_model',
        'rate',
        'ibs_stock',
        'low_warning',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'ibs_stock' => 'integer',
            'low_warning' => 'integer',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(ProductControlState::class, 'product_control_state_id');
    }
}
