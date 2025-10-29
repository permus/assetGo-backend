<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveMaintenanceJob extends Model
{
    protected $table = 'predictive_maintenance_jobs';

    protected $fillable = [
        'job_id',
        'company_id',
        'status',
        'progress',
        'total_assets',
        'predictions_generated',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'progress' => 'integer',
        'total_assets' => 'integer',
        'predictions_generated' => 'integer',
    ];

    /**
     * Get the company that this job belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

