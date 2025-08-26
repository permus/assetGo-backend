<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePlanChecklist extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'maintenance_plans_checklists';

    protected $fillable = [
        'maintenance_plan_id',
        'title',
        'type',
        'description',
        'is_required',
        'is_safety_critical',
        'is_photo_required',
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_safety_critical' => 'boolean',
        'is_photo_required' => 'boolean',
        'order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }
}


