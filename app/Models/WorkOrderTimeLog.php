<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrderTimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'user_id',
        'company_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'hourly_rate',
        'total_cost',
        'description',
        'activity_type',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'hourly_rate' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


