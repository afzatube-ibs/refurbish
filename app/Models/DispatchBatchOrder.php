<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchBatchOrder extends Model
{
    protected $fillable = [
        'dispatch_batch_id',
        'order_id',
        'order_no',
        'customer_name',
        'phone',
        'courier',
        'consignment_id',
        'total_qty',
        'total_supplier_cost',
    ];

    protected function casts(): array
    {
        return [
            'total_supplier_cost' => 'decimal:2',
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
}
