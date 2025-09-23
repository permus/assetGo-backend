<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRecommendation extends Model
{
    use HasFactory;

    protected $table = 'ai_recommendations';

    protected $fillable = [
        'company_id',
        'rec_type',
        'title',
        'description',
        'impact',
        'priority',
        'estimated_savings',
        'implementation_cost',
        'roi',
        'payback_period',
        'timeline',
        'actions',
        'confidence',
        'implemented'
    ];

    protected $casts = [
        'estimated_savings' => 'decimal:2',
        'implementation_cost' => 'decimal:2',
        'roi' => 'decimal:2',
        'confidence' => 'decimal:2',
        'implemented' => 'boolean',
        'actions' => 'array',
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
     * Scope for type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('rec_type', $type);
    }

    /**
     * Scope for priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for impact
     */
    public function scopeByImpact($query, $impact)
    {
        return $query->where('impact', $impact);
    }

    /**
     * Scope for implemented status
     */
    public function scopeImplemented($query, $implemented = true)
    {
        return $query->where('implemented', $implemented);
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for minimum confidence
     */
    public function scopeMinConfidence($query, $minConfidence)
    {
        return $query->where('confidence', '>=', $minConfidence);
    }

    /**
     * Get computed ROI
     */
    public function getComputedRoiAttribute()
    {
        if (!$this->estimated_savings || !$this->implementation_cost || $this->implementation_cost <= 0) {
            return null;
        }
        
        $net = $this->estimated_savings - $this->implementation_cost;
        return ($net / $this->implementation_cost) * 100;
    }

    /**
     * Get computed payback period
     */
    public function getComputedPaybackPeriodAttribute()
    {
        if (!$this->estimated_savings || !$this->implementation_cost || $this->estimated_savings <= 0) {
            return null;
        }
        
        $months = max(1, round(($this->implementation_cost / ($this->estimated_savings / 12))));
        return "{$months} months";
    }
}
