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
        'category_id',
        'response_time_hours',
        'containment_time_hours',
        'completion_time_hours',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'response_time_hours' => 'decimal:2',
        'containment_time_hours' => 'decimal:2',
        'completion_time_hours' => 'decimal:2',
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

    /**
     * Get the category that this SLA applies to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkOrderCategory::class, 'category_id');
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Check if this SLA definition matches a work order
     * Matches based on applies_to, category_id, priority_level, and is_active
     */
    public function matchesWorkOrder(WorkOrder $workOrder): bool
    {
        // Check applies_to
        if (!in_array($this->applies_to, ['work_orders', 'both'])) {
            return false;
        }

        // Check if SLA is active
        if (!$this->is_active) {
            return false;
        }

        // Check category match (both null or same ID)
        if ($this->category_id !== $workOrder->category_id) {
            return false;
        }

        // Check priority match by slug
        if ($this->priority_level) {
            // Load priority relationship if not already loaded
            if (!$workOrder->relationLoaded('priority')) {
                $workOrder->load('priority');
            }
            
            $workOrderPrioritySlug = $workOrder->priority?->slug;
            if ($workOrderPrioritySlug !== $this->priority_level) {
                return false;
            }
        }

        return true;
    }
}
