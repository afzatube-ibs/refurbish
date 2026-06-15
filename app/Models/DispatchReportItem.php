<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchReportItem extends Model
{
    protected $fillable = [
        'dispatch_report_id',
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

    public function dispatchReport(): BelongsTo
    {
        return $this->belongsTo(DispatchReport::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
