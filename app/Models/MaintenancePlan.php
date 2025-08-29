<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'priority_id',
        'sort',
        'descriptions',
        'category_id',
        'plan_type',
        'estimeted_duration',
        'instractions',
        'safety_notes',
        'asset_ids',
        'frequency_type',
        'frequency_value',
        'frequency_unit',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'asset_ids' => 'array',
        'frequency_value' => 'integer',
    ];

    public function checklists(): HasMany
    {
        return $this->hasMany(MaintenancePlanChecklist::class)
            ->orderBy('order')
            ->orderBy('id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleMaintenance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(WorkOrderPriority::class, 'priority_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkOrderCategory::class, 'category_id');
    }
}


