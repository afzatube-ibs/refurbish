<?php

namespace App\Models;

use App\Enums\SfmOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'supplier_id',
        'source_order_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'sale_amount',
        'current_oc_status',
        'sfm_status',
        'courier_status',
        'consignment_id',
        'courier_name',
        'oc_created_at',
        'source_snapshot',
        'stock_deducted',
    ];

    protected function casts(): array
    {
        return [
            'sale_amount' => 'decimal:2',
            'sfm_status' => SfmOrderStatus::class,
            'oc_created_at' => 'datetime',
            'source_snapshot' => 'array',
            'stock_deducted' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function dispatchReports(): HasMany
    {
        return $this->hasMany(DispatchReport::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class);
    }
}
