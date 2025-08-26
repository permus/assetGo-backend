<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleMaintenance extends Model
{
    use HasFactory;

    protected $table = 'schedule_maintenance';

    protected $fillable = [
        'maintenance_plan_id',
        'asset_ids',
        'start_date',
        'due_date',
        'status',
        'priority_id',
    ];

    protected $casts = [
        'asset_ids' => 'array',
        'start_date' => 'date',
        'due_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ScheduleMaintenanceAssigned::class, 'schedule_maintenance_id');
    }
}


