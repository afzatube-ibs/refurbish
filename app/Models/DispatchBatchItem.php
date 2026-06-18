<?php

namespace App\Models;

use App\Enums\DispatchBatchItemCostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchBatchItem extends Model
{
    protected $fillable = [
        'dispatch_batch_id',
        'order_id',
        'order_item_id',
        'product_name',
        'model',
        'ibs_model',
        'qty',
        'supplier_unit_cost',
        'supplier_total_cost',
        'cost_status',
    ];

    protected function casts(): array
    {
        return [
            'supplier_unit_cost' => 'decimal:2',
            'supplier_total_cost' => 'decimal:2',
            'cost_status' => DispatchBatchItemCostStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DispatchBatch::class, 'dispatch_batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
