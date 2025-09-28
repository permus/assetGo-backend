<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'owner_id',
        'name',
        'description',
        'report_key',
        'definition',
        'default_filters',
        'is_shared',
        'is_public'
    ];

    protected $casts = [
        'definition' => 'array',
        'default_filters' => 'array',
        'is_shared' => 'boolean',
        'is_public' => 'boolean',
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
     * Relationship with Owner (User)
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Relationship with Report Runs
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }

    /**
     * Relationship with Schedules
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }

    /**
     * Scope for company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for owner
     */
    public function scopeForOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope for shared templates
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope for public templates
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for report key
     */
    public function scopeByReportKey($query, $reportKey)
    {
        return $query->where('report_key', $reportKey);
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for accessible templates (owned by user or shared)
     */
    public function scopeAccessibleBy($query, $userId, $companyId)
    {
        return $query->where('company_id', $companyId)
                    ->where(function($q) use ($userId) {
                        $q->where('owner_id', $userId)
                          ->orWhere('is_shared', true);
                    });
    }

    /**
     * Get report category from report_key
     */
    public function getCategoryAttribute(): string
    {
        if (!$this->report_key) {
            return 'custom';
        }

        return explode('.', $this->report_key)[0] ?? 'custom';
    }

    /**
     * Get category display name
     */
    public function getCategoryDisplayNameAttribute(): string
    {
        return match($this->category) {
            'assets' => 'Asset Reports',
            'maintenance' => 'Maintenance Reports',
            'inventory' => 'Inventory Reports',
            'financial' => 'Financial Reports',
            'custom' => 'Custom Reports',
            default => 'Unknown'
        };
    }

    /**
     * Get template type (standard or custom)
     */
    public function getTypeAttribute(): string
    {
        return $this->report_key ? 'standard' : 'custom';
    }

    /**
     * Get last run date
     */
    public function getLastRunAtAttribute(): ?string
    {
        $lastRun = $this->runs()->latest()->first();
        return $lastRun?->created_at?->toISOString();
    }

    /**
     * Get run count
     */
    public function getRunCountAttribute(): int
    {
        return $this->runs()->count();
    }

    /**
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        $totalRuns = $this->runs()->count();
        if ($totalRuns === 0) {
            return 0;
        }

        $successfulRuns = $this->runs()->successful()->count();
        return round(($successfulRuns / $totalRuns) * 100, 2);
    }

    /**
     * Check if template is owned by user
     */
    public function isOwnedBy($userId): bool
    {
        return $this->owner_id === $userId;
    }

    /**
     * Check if template is accessible by user
     */
    public function isAccessibleBy($userId): bool
    {
        return $this->isOwnedBy($userId) || $this->is_shared;
    }
}
