<?php

namespace App\Models;

use App\Enums\SfmOrderStatus;
use Illuminate\Database\Eloquent\Model;

class OrderStatusMapping extends Model
{
    protected $fillable = [
        'source_status_id',
        'source_status_name',
        'sfm_status',
    ];

    protected function casts(): array
    {
        return [
            'source_status_id' => 'integer',
            'sfm_status' => SfmOrderStatus::class,
        ];
    }
}
