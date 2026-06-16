<?php

namespace App\Models\ProductMap;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'stock_adjustment_history';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'variant_id',
        'old_stock',
        'new_stock',
        'difference',
        'reason',
        'note',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_stock' => 'integer',
            'new_stock' => 'integer',
            'difference' => 'integer',
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
