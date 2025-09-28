<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ReportSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'template_id',
        'name',
        'description',
        'rrule',
        'timezone',
        'delivery_email',
        'delivery_options',
        'enabled',
        'last_run_at',
        'next_run_at'
    ];

    protected $casts = [
        'delivery_options' => 'array',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship with Company
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relationship with Template
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    /**
     * Scope for company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for enabled schedules
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope for due schedules
     */
    public function scopeDue($query)
    {
        return $query->where('enabled', true)
                    ->where('next_run_at', '<=', now());
    }

    /**
     * Scope for template
     */
    public function scopeForTemplate($query, $templateId)
    {
        return $query->where('template_id', $templateId);
    }

    /**
     * Get frequency display name
     */
    public function getFrequencyDisplayNameAttribute(): string
    {
        // Parse RRULE to get frequency
        if (preg_match('/FREQ=(\w+)/', $this->rrule, $matches)) {
            $freq = strtoupper($matches[1]);
            return match($freq) {
                'DAILY' => 'Daily',
                'WEEKLY' => 'Weekly',
                'MONTHLY' => 'Monthly',
                'YEARLY' => 'Yearly',
                default => 'Custom'
            };
        }

        return 'Custom';
    }

    /**
     * Get next run time formatted
     */
    public function getNextRunFormattedAttribute(): string
    {
        if (!$this->next_run_at) {
            return 'Not scheduled';
        }

        return $this->next_run_at->format('M j, Y g:i A');
    }

    /**
     * Get last run time formatted
     */
    public function getLastRunFormattedAttribute(): string
    {
        if (!$this->last_run_at) {
            return 'Never';
        }

        return $this->last_run_at->format('M j, Y g:i A');
    }

    /**
     * Check if schedule is due for execution
     */
    public function getIsDueAttribute(): bool
    {
        return $this->enabled && 
               $this->next_run_at && 
               $this->next_run_at->isPast();
    }

    /**
     * Check if schedule is active
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->enabled && $this->template;
    }

    /**
     * Get delivery email list
     */
    public function getDeliveryEmailsAttribute(): array
    {
        if (!$this->delivery_email) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $this->delivery_email)));
    }

    /**
     * Calculate next run time based on RRULE
     */
    public function calculateNextRun(): ?Carbon
    {
        if (!$this->enabled || !$this->rrule) {
            return null;
        }

        try {
            // Simple RRULE parsing for common patterns
            // This is a basic implementation - for production, consider using a proper RRULE library
            $now = Carbon::now($this->timezone);
            
            if (preg_match('/FREQ=(\w+)/', $this->rrule, $matches)) {
                $frequency = strtoupper($matches[1]);
                
                switch ($frequency) {
                    case 'DAILY':
                        return $now->addDay();
                    case 'WEEKLY':
                        return $now->addWeek();
                    case 'MONTHLY':
                        return $now->addMonth();
                    case 'YEARLY':
                        return $now->addYear();
                }
            }

            // If we can't parse, return null
            return null;
        } catch (\Exception $e) {
            \Log::error('Failed to calculate next run time', [
                'schedule_id' => $this->id,
                'rrule' => $this->rrule,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update next run time
     */
    public function updateNextRun(): bool
    {
        $nextRun = $this->calculateNextRun();
        if ($nextRun) {
            $this->next_run_at = $nextRun;
            return $this->save();
        }
        return false;
    }

    /**
     * Mark as executed
     */
    public function markAsExecuted(): bool
    {
        $this->last_run_at = now();
        $this->updateNextRun();
        return $this->save();
    }

    /**
     * Enable schedule
     */
    public function enable(): bool
    {
        $this->enabled = true;
        $this->updateNextRun();
        return $this->save();
    }

    /**
     * Disable schedule
     */
    public function disable(): bool
    {
        $this->enabled = false;
        $this->next_run_at = null;
        return $this->save();
    }
}
