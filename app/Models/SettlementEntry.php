<?php

namespace App\Models;

use App\Enums\CollectionSource;
use App\Enums\SettlementEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SettlementEntry extends Model
{
    protected $fillable = [
        'supplier_id',
        'connection_id',
        'order_id',
        'entry_type',
        'amount',
        'entry_date',
        'reference',
        'notes',
        'recorded_by',
        'settlement_batch_id',
        'collection_source',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => SettlementEntryType::class,
            'collection_source' => CollectionSource::class,
            'amount' => 'decimal:2',
            'entry_date' => 'date',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(SupplierLedgerEntry::class);
    }

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class);
    }
}
