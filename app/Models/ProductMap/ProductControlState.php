<?php

namespace App\Models\ProductMap;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductControlState extends Model
{
    protected $fillable = [
        'supplier_id',
        'source_product_id',
        'ibs_model',
        'sm_model',
        'product_category',
        'rate',
        'low_warning',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'low_warning' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductControlVariant::class);
    }
}
