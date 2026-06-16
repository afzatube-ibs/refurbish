<?php

namespace App\Models\ProductMap;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRateHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'product_rate_history';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'variant_id',
        'old_rate',
        'new_rate',
        'difference',
        'effective_from',
        'changed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:2',
            'new_rate' => 'decimal:2',
            'difference' => 'decimal:2',
            'effective_from' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
