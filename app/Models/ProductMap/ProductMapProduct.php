<?php

namespace App\Models\ProductMap;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMapProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'source_product_id',
        'oc_snapshot',
        'source_product_snapshot',
        'oc_fingerprint',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'oc_snapshot' => 'array',
            'source_product_snapshot' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
