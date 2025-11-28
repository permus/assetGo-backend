<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'applies_to',
        'priority_level',
        'category',
        'response_time_hours',
        'containment_time_hours',
        'completion_time_hours',
        'business_hours_only',
        'working_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'response_time_hours' => 'decimal:2',
        'containment_time_hours' => 'decimal:2',
        'completion_time_hours' => 'decimal:2',
        'business_hours_only' => 'boolean',
        'working_days' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns this SLA definition
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created this SLA definition
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active SLA definitions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get SLA definitions for work orders
     */
    public function scopeForWorkOrders($query)
    {
        return $query->whereIn('applies_to', ['work_orders', 'both']);
    }

    /**
     * Scope to get SLA definitions for maintenance
     */
    public function scopeForMaintenance($query)
    {
        return $query->whereIn('applies_to', ['maintenance', 'both']);
    }
}
