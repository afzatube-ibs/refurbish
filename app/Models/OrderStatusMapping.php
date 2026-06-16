<?php

namespace App\Models;

use App\Enums\OrderSyncRole;
use App\Enums\SfmOrderStatus;
use Illuminate\Database\Eloquent\Model;

class OrderStatusMapping extends Model
{
    protected $fillable = [
        'source_status_id',
        'source_status_name',
        'oc_selected',
        'sfm_status',
        'sync_role',
    ];

    protected function casts(): array
    {
        return [
            'source_status_id' => 'integer',
            'oc_selected' => 'boolean',
            'sfm_status' => SfmOrderStatus::class,
            'sync_role' => OrderSyncRole::class,
        ];
    }

    public function scopeSyncActive($query)
    {
        return $query
            ->where('oc_selected', true)
            ->where('sync_role', '!=', OrderSyncRole::Ignore);
    }

    public function scopeImportTrigger($query)
    {
        return $query
            ->where('oc_selected', true)
            ->where('sync_role', OrderSyncRole::ImportTrigger)
            ->where('sfm_status', SfmOrderStatus::New);
    }

    public function scopeUpdateExisting($query)
    {
        return $query
            ->where('oc_selected', true)
            ->where('sync_role', OrderSyncRole::UpdateExisting);
    }

    public function isSyncActive(): bool
    {
        return $this->oc_selected && $this->sync_role !== OrderSyncRole::Ignore;
    }

    public function effectiveSyncRole(): OrderSyncRole
    {
        if (! $this->oc_selected) {
            return OrderSyncRole::Ignore;
        }

        return $this->sync_role ?? OrderSyncRole::Ignore;
    }
}
