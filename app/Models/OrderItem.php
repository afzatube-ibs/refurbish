<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'supplier_product_id',
        'source_product_id',
        'product_name',
        'model',
        'variant_label',
        'quantity',
        'sale_price',
        'supplier_product_cost_snapshot',
        'cost_snapshotted_at',
        'item_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sale_price' => 'decimal:2',
            'supplier_product_cost_snapshot' => 'decimal:2',
            'cost_snapshotted_at' => 'datetime',
            'item_status' => OrderItemStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
