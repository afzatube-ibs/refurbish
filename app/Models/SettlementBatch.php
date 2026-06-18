<?php

namespace App\Models;

use App\Enums\SettlementBatchDirection;
use App\Enums\SettlementBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementBatch extends Model
{
    protected $fillable = [
        'batch_no',
        'supplier_id',
        'connection_id',
        'opening_balance',
        'closing_balance',
        'direction',
        'status',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'direction' => SettlementBatchDirection::class,
            'status' => SettlementBatchStatus::class,
            'closed_at' => 'datetime',
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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    public function settlementEntries(): HasMany
    {
        return $this->hasMany(SettlementEntry::class);
    }

    public function displayAmount(): float
    {
        return round(abs((float) $this->closing_balance), 2);
    }
}
