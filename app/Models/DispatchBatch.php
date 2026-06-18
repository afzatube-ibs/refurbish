<?php

namespace App\Models;

use App\Enums\DispatchBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchBatch extends Model
{
    protected $fillable = [
        'batch_no',
        'supplier_id',
        'connection_id',
        'dispatch_date',
        'status',
        'total_orders',
        'total_items',
        'total_qty',
        'total_supplier_cost',
        'created_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
            'status' => DispatchBatchStatus::class,
            'total_supplier_cost' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batchOrders(): HasMany
    {
        return $this->hasMany(DispatchBatchOrder::class);
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(DispatchBatchItem::class);
    }

    public function dispatchReports(): HasMany
    {
        return $this->hasMany(DispatchReport::class);
    }
}
