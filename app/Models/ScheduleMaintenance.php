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
        'assigned_user_id',
        'assigned_role_id',
        'assigned_team_id',
        'auto_generated_wo_ids',
    ];

    protected $casts = [
        'asset_ids' => 'array',
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'auto_generated_wo_ids' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ScheduleMaintenanceAssigned::class, 'schedule_maintenance_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}


