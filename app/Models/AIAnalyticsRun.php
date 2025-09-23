<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAnalyticsRun extends Model
{
    use HasFactory;

    protected $table = 'ai_analytics_runs';

    protected $fillable = [
        'company_id',
        'payload',
        'health_score'
    ];

    protected $casts = [
        'payload' => 'array',
        'health_score' => 'decimal:2',
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
     * Scope for company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for latest run
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get health score label
     */
    public function getHealthScoreLabelAttribute(): string
    {
        $score = $this->health_score;
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Good';
        if ($score >= 70) return 'Fair';
        if ($score >= 60) return 'Poor';
        return 'Critical';
    }

    /**
     * Get health score color class
     */
    public function getHealthScoreColorClassAttribute(): string
    {
        $score = $this->health_score;
        if ($score >= 90) return 'health-excellent';
        if ($score >= 80) return 'health-good';
        if ($score >= 70) return 'health-fair';
        if ($score >= 60) return 'health-poor';
        return 'health-critical';
    }
}
