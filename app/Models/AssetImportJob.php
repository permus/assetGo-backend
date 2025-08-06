<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
     * Get the progress percentage with stability checks
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_assets === 0) {
            return 0;
        }

        // Ensure processed_assets doesn't exceed total_assets
        $processed = min($this->processed_assets, $this->total_assets);
        $percentage = round(($processed / $this->total_assets) * 100, 2);
        
        // Ensure percentage is between 0 and 100
        return max(0, min(100, $percentage));
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
     * Update progress with atomic database operations to prevent race conditions
     */
    public function updateProgress(int $processed, int $successful = null, int $failed = null, array $errors = null): void
    {
        // Use database transaction to ensure atomic updates
        \DB::transaction(function () use ($processed, $successful, $failed, $errors) {
            // Lock the row to prevent concurrent updates
            $job = self::where('id', $this->id)->lockForUpdate()->first();
            
            if (!$job) {
                return; // Job was deleted
            }

            // Only update if processed count is greater than current (prevents going backwards)
            if ($processed >= $job->processed_assets) {
                $updateData = [
                    'processed_assets' => $processed,
                    'updated_at' => now()
                ];
                
                if ($successful !== null) {
                    $updateData['successful_imports'] = $successful;
                }
                
                if ($failed !== null) {
                    $updateData['failed_imports'] = $failed;
                }
                
                if ($errors !== null) {
                    $updateData['errors'] = json_encode($errors);
                }

                // Update status based on progress
                if ($processed >= $job->total_assets) {
                    $updateData['status'] = 'completed';
                    $updateData['completed_at'] = now();
                } elseif ($job->status === 'pending') {
                    $updateData['status'] = 'processing';
                    $updateData['started_at'] = now();
                }

                // Perform atomic update
                self::where('id', $this->id)->update($updateData);
                
                // Refresh the model instance
                $this->refresh();
            }
        });
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
     * Mark as completed and ensure progress shows 100%
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->processed_assets = $this->total_assets; // Ensure 100% progress
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