<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'source_product_id',
        'image',
        'model',
        'name',
        'type',
        'stock',
        'supplier_cost',
        'supplier_model',
        'supplier_stock',
        'low_warning',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'supplier_cost' => 'decimal:2',
            'supplier_stock' => 'integer',
            'low_warning' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(SupplierProductVariant::class);
    }
}
