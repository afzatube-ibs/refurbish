<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_id',
        'order_item_id',
        'quantity',
        'supplier_cost_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'supplier_cost_snapshot' => 'decimal:2',
        ];
    }

    public function returnRecord(): BelongsTo
    {
        return $this->belongsTo(ReturnModel::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
