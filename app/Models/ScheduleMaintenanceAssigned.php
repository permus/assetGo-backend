<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleMaintenanceAssigned extends Model
{
    use HasFactory;

    protected $table = 'schedule_maintenance_assigned';

    protected $fillable = [
        'schedule_maintenance_id',
        'team_id',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduleMaintenance::class, 'schedule_maintenance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_id');
    }

    public function responses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MaintenanceChecklistResponse::class, 'schedule_maintenance_assigned_id');
    }
}


