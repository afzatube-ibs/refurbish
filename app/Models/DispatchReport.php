<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchReport extends Model
{
    protected $fillable = [
        'order_id',
        'supplier_id',
        'dispatch_date',
        'courier',
        'consignment_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchReportItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
