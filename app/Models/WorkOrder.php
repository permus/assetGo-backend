<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'asset_id',
        'location_id',
        'assigned_to',
        'assigned_by',
        'created_by',
        'company_id',
        'estimated_hours',
        'actual_hours',
        'notes',
        'meta',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'meta' => 'array',
    ];

    protected $appends = ['is_overdue', 'days_until_due', 'days_since_created', 'resolution_time_days'];

    /**
     * Check if work order is overdue
     */
    public function getIsOverdueAttribute()
    {
        if (!$this->due_date || $this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }
        return $this->due_date->isPast();
    }

    /**
     * Get days until due (negative if overdue)
     */
    public function getDaysUntilDueAttribute()
    {
        if (!$this->due_date) {
            return null;
        }
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get days since created
     */
    public function getDaysSinceCreatedAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Get resolution time in days
     */
    public function getResolutionTimeDaysAttribute()
    {
        if (!$this->completed_at) {
            return null;
        }
        return $this->created_at->diffInDays($this->completed_at);
    }

    /**
     * Scope for company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for overdue work orders
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope for assigned to user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for created by user
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Relationship with Asset
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Relationship with Location
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Relationship with assigned user
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relationship with user who assigned
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Relationship with user who created
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Boot method to handle model events
     */
    protected static function booted()
    {
        static::creating(function ($workOrder) {
            if (!$workOrder->created_by && auth()->check()) {
                $workOrder->created_by = auth()->id();
            }
        });

        static::updating(function ($workOrder) {
            // Set completed_at when status changes to completed
            if ($workOrder->isDirty('status') && $workOrder->status === 'completed' && !$workOrder->completed_at) {
                $workOrder->completed_at = now();
            }
        });
    }
}
