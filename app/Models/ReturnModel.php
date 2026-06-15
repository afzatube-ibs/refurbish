<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnModel extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'supplier_id',
        'return_status',
        'received_date',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'return_status' => ReturnStatus::class,
            'received_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
