<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AssetImportJob extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'company_id',
        'status',
        'total_assets',
        'processed_assets',
        'successful_imports',
        'failed_imports',
        'import_data',
        'errors',
        'imported_assets',
        'started_at',
        'completed_at',
        'error_message'
    ];

    protected $casts = [
        'import_data' => 'array',
        'errors' => 'array',
        'imported_assets' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->job_id)) {
                $model->job_id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the user who initiated the import
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company for this import
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_assets === 0) {
            return 0;
        }

        return round(($this->processed_assets / $this->total_assets) * 100, 2);
    }

    /**
     * Check if the import is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return in_array($this->status, ['completed', 'failed']);
    }

    /**
     * Check if the import is in progress
     */
    public function getIsProcessingAttribute(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Update progress
     */
    public function updateProgress(int $processed, int $successful = null, int $failed = null, array $errors = null): void
    {
        $this->processed_assets = $processed;
        
        if ($successful !== null) {
            $this->successful_imports = $successful;
        }
        
        if ($failed !== null) {
            $this->failed_imports = $failed;
        }
        
        if ($errors !== null) {
            $this->errors = $errors;
        }

        // Update status based on progress
        if ($processed >= $this->total_assets) {
            $this->status = 'completed';
            $this->completed_at = now();
        } elseif ($this->status === 'pending') {
            $this->status = 'processing';
            $this->started_at = now();
        }

        $this->save();
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Add imported asset ID
     */
    public function addImportedAsset(int $assetId): void
    {
        $importedAssets = $this->imported_assets ?? [];
        $importedAssets[] = $assetId;
        $this->imported_assets = $importedAssets;
        $this->save();
    }
}