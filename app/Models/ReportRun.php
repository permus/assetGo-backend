<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'template_id',
        'report_key',
        'params',
        'filters',
        'format',
        'status',
        'row_count',
        'file_path',
        'error_message',
        'started_at',
        'completed_at',
        'execution_time_ms'
    ];

    protected $casts = [
        'params' => 'array',
        'filters' => 'array',
        'row_count' => 'integer',
        'execution_time_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
     * Relationship with User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Scope for user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for report key
     */
    public function scopeByReportKey($query, $reportKey)
    {
        return $query->where('report_key', $reportKey);
    }

    /**
     * Scope for format
     */
    public function scopeByFormat($query, $format)
    {
        return $query->where('format', $format);
    }

    /**
     * Scope for successful runs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed runs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for recent runs
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get download URL for exported files
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path || $this->status !== 'success') {
            return null;
        }

        return \Storage::disk('local')->url($this->file_path);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'queued' => 'Queued',
            'running' => 'Running',
            'success' => 'Completed',
            'failed' => 'Failed',
            default => 'Unknown'
        };
    }

    /**
     * Get status color class
     */
    public function getStatusColorClassAttribute(): string
    {
        return match($this->status) {
            'queued' => 'status-queued',
            'running' => 'status-running',
            'success' => 'status-success',
            'failed' => 'status-failed',
            default => 'status-unknown'
        };
    }

    /**
     * Get execution time in human readable format
     */
    public function getExecutionTimeFormattedAttribute(): string
    {
        if (!$this->execution_time_ms) {
            return 'N/A';
        }

        if ($this->execution_time_ms < 1000) {
            return $this->execution_time_ms . 'ms';
        }

        return number_format($this->execution_time_ms / 1000, 2) . 's';
    }

    /**
     * Check if run is completed (success or failed)
     */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['success', 'failed']);
    }

    /**
     * Check if run is still processing
     */
    public function getIsProcessingAttribute(): bool
    {
        return in_array($this->status, ['queued', 'running']);
    }
}
