<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLedgerEntry extends Model
{
    protected $fillable = [
        'supplier_id',
        'order_id',
        'connection_id',
        'settlement_entry_id',
        'source_type',
        'source_id',
        'entry_date',
        'type',
        'amount',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
            'type' => LedgerEntryType::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function settlementEntry(): BelongsTo
    {
        return $this->belongsTo(SettlementEntry::class);
    }
}
