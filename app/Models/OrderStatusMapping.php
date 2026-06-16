<?php

namespace App\Models;

use App\Enums\SfmOrderStatus;
use Illuminate\Database\Eloquent\Model;

class OrderStatusMapping extends Model
{
    protected $fillable = [
        'source_status_id',
        'source_status_name',
        'oc_selected',
        'sfm_status',
    ];

    protected function casts(): array
    {
        return [
            'source_status_id' => 'integer',
            'oc_selected' => 'boolean',
            'sfm_status' => SfmOrderStatus::class,
        ];
    }
}
