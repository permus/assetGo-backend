<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveMaintenance extends Model
{
    use HasFactory;

    protected $table = 'predictive_maintenance';

    protected $fillable = [
        'asset_id',
        'risk_level',
        'predicted_failure_date',
        'confidence',
        'recommended_action',
        'estimated_cost',
        'preventive_cost',
        'savings',
        'factors',
        'timeline',
        'company_id',
    ];

    protected $casts = [
        'predicted_failure_date' => 'date',
        'confidence' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'preventive_cost' => 'decimal:2',
        'savings' => 'decimal:2',
        'factors' => 'array',
        'timeline' => 'array',
    ];

    protected $dates = [
        'predicted_failure_date',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the asset that this prediction belongs to.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the company that this prediction belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to filter by risk level.
     */
    public function scopeRiskLevel($query, string $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by minimum confidence.
     */
    public function scopeMinConfidence($query, float $minConfidence)
    {
        return $query->where('confidence', '>=', $minConfidence);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('predicted_failure_date', '>=', $from);
        }
        if ($to) {
            $query->where('predicted_failure_date', '<=', $to);
        }
        return $query;
    }

    /**
     * Get the risk level color class.
     */
    public function getRiskLevelColorAttribute(): string
    {
        return match ($this->risk_level) {
            'high' => 'text-red-600 bg-red-50',
            'medium' => 'text-amber-600 bg-amber-50',
            'low' => 'text-green-600 bg-green-50',
            default => 'text-gray-600 bg-gray-50',
        };
    }

    /**
     * Get the confidence level description.
     */
    public function getConfidenceLevelAttribute(): string
    {
        if ($this->confidence >= 80) return 'High';
        if ($this->confidence >= 60) return 'Medium';
        return 'Low';
    }

    /**
     * Calculate ROI percentage.
     */
    public function getRoiAttribute(): float
    {
        if ($this->preventive_cost <= 0) return 0;
        return round(($this->savings / $this->preventive_cost) * 100, 2);
    }
}