<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetImportJob;
use App\Models\AssetStatus;
use App\Models\AssetTag;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use App\Services\QRCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkAssetImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;
    public $uniqueFor = 3600; // Job is unique for 1 hour

    protected $importJob;
    protected $qrCodeService;

    /**
     * Create a new job instance.
     */
    public function __construct(AssetImportJob $importJob)
    {
        $this->importJob = $importJob;
        $this->qrCodeService = app(QRCodeService::class);
    }

    /**
     * Get the unique ID for the job to prevent duplicates
     */
    public function uniqueId(): string
    {
        return $this->importJob->job_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set memory limit to 512MB for bulk import processing
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        
        try {
            // Ensure only one worker processes this job
            \DB::transaction(function () {
                $job = AssetImportJob::where('id', $this->importJob->id)
                    ->lockForUpdate()
                    ->first();
                
                if (!$job) {
                    throw new \Exception("Job {$this->importJob->job_id} not found");
                }
                
                // Only prevent processing if job is completed, failed, or cancelled
                if (in_array($job->status, ['completed', 'failed', 'cancelled'])) {
                    throw new \Exception("Job {$this->importJob->job_id} is already completed or failed");
                }
                
                // If job is pending, mark it as processing
                if ($job->status === 'pending') {
                    $job->update([
                        'status' => 'processing',
                        'started_at' => now()
                    ]);
                }
                
                $this->importJob = $job;
            });

            Log::info("Starting bulk asset import job: {$this->importJob->job_id}");

            $user = $this->importJob->user;
            $assetsData = $this->importJob->import_data;
            $errors = [];
            $successful = 0;
            $failed = 0;
            $processed = 0;

            // Process assets in smaller batches to avoid memory issues
            $batchSize = 25; // Reduced batch size for better memory management
            $totalAssets = count($assetsData);
            $batches = array_chunk($assetsData, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                foreach ($batch as $index => $assetData) {
                    $actualIndex = ($batchIndex * $batchSize) + $index;
                    
                    try {
                        DB::beginTransaction();
                        
                        $asset = $this->processAsset($assetData, $user);
                        
                        if ($asset) {
                            $this->importJob->addImportedAsset($asset->id);
                            $successful++;
                        }
                        
                        DB::commit();
                        
                    } catch (\Exception $e) {
                        DB::rollBack();
                        $failed++;
                        $errors[] = [
                            'index' => $actualIndex + 1,
                            'name' => $assetData['name'] ?? 'Unknown',
                            'error' => $e->getMessage()
                        ];
                        
                        Log::error("Asset import error for asset {$actualIndex}: " . $e->getMessage());
                    }
                    
                    $processed++;
                    
                    // Update progress every 25 assets or at the end to reduce race conditions
                    if ($processed % 25 === 0 || $processed === $totalAssets) {
                        $oldProcessed = $this->importJob->processed_assets;
                        $this->importJob->updateProgress($processed, $successful, $failed, $errors);
                        
                        // Log progress updates for debugging
                        Log::info("Progress updated for job {$this->importJob->job_id}: {$oldProcessed} -> {$processed}/{$totalAssets} (" . round(($processed/$totalAssets)*100, 2) . "%)");
                    }
                }
                
                // Force garbage collection after each batch
                gc_collect_cycles();
                
                // Small delay between batches to prevent overwhelming the system
                usleep(100000); // 0.1 seconds
            }

            // Final progress update to ensure 100% completion
            $this->importJob->updateProgress($totalAssets, $successful, $failed, $errors);
            Log::info("Final progress update: {$processed}/{$totalAssets} (100%)");

            $this->importJob->markAsCompleted();
            
            Log::info("Completed bulk asset import job: {$this->importJob->job_id}. Success: {$successful}, Failed: {$failed}");

        } catch (\Exception $e) {
            Log::error("Bulk asset import job failed: {$this->importJob->job_id}. Error: " . $e->getMessage());
            $this->importJob->markAsFailed($e->getMessage());
            throw $e;
        } finally {
            // Always clean up and safely restore memory limit
            gc_collect_cycles();
            $this->safeRestoreMemoryLimit($originalMemoryLimit);
        }
    }

    /**
     * Process a single asset
     */
    protected function processAsset(array $assetData, User $user): ?Asset
    {
        // Generate unique asset ID
        $assetId = $assetData['asset_id'] ?? null;
        
        // Check if asset ID is empty, null, or "N/A" (case insensitive)
        if (!$assetId || trim($assetId) === '' || strtoupper(trim($assetId)) === 'N/A') {
            $assetId = 'AST-' . strtoupper(substr(uniqid(), -8));
            
            // Ensure uniqueness within the company
            while (Asset::where('asset_id', $assetId)->where('company_id', $user->company_id)->exists()) {
                $assetId = 'AST-' . strtoupper(substr(uniqid(), -8));
            }
        } else {
            // If asset ID is provided but already exists, make it unique
            $originalAssetId = trim($assetId);
            $counter = 1;
            
            while (Asset::where('asset_id', $assetId)->where('company_id', $user->company_id)->exists()) {
                $assetId = $originalAssetId . '-' . $counter;
                $counter++;
                
                // Prevent infinite loop - if counter gets too high, generate new ID
                if ($counter > 9999) {
                    $assetId = 'AST-' . strtoupper(substr(uniqid(), -8));
                    break;
                }
            }
        }

        // Find or create category
        $category = null;
        if (!empty($assetData['category'])) {
            $category = AssetCategory::firstOrCreate(
                ['name' => $assetData['category']],
                [
                    'description' => $assetData['category'] . ' category',
                    'icon' => 'https://unpkg.com/lucide-static/icons/tag.svg'
                ]
            );
        }

        // Find or create S/M Type as asset category
        $smTypeCategory = null;
        if (!empty($assetData['s_m_type'])) {
            $smTypeCategory = AssetCategory::firstOrCreate(
                ['name' => $assetData['s_m_type']],
                [
                    'description' => $assetData['s_m_type'] . ' S/M Type category',
                    'icon' => 'https://unpkg.com/lucide-static/icons/thumbs-up.svg'
                ]
            );
        }

        // Handle hierarchical location structure
        $location = $this->processLocationHierarchy($assetData, $user);

        // Find or create department
        $department = null;
        if (!empty($assetData['department'])) {
            $department = Department::firstOrCreate(
                [
                    'name' => $assetData['department'],
                    'company_id' => $user->company_id
                ],
                [
                    'description' => $assetData['department'] . ' department',
                    'icon' => 'https://unpkg.com/lucide-static/icons/users.svg'
                ]
            );
        }

        // Find or create asset type
        $assetType = null;
        if (!empty($assetData['type'])) {
            $assetType = AssetType::firstOrCreate(
                ['name' => $assetData['type']],
                ['icon' => 'https://unpkg.com/lucide-static/icons/tag.svg']
            );
        } else {
            // Default to "Fixed Assets" if no type provided
            $assetType = AssetType::firstOrCreate(
                ['name' => 'Fixed Assets'],
                ['icon' => 'https://unpkg.com/lucide-static/icons/tag.svg']
            );
        }

        // Create the asset
        $asset = Asset::create([
            'asset_id' => $assetId,
            'name' => $assetData['name'],
            'description' => $assetData['asset_description'] ?? $assetData['description'] ?? '',
            'category_id' => $category?->id ?? $smTypeCategory?->id,
            'type' => $assetType?->name ?? $assetData['type'] ?? null,
            'serial_number' => $assetData['serial_number'] ?? null,
            'model' => $assetData['model'] ?? null,
            'manufacturer' => $assetData['brand_make'] ?? $assetData['manufacturer'] ?? null,
            'purchase_date' => $assetData['purchase_date'] ?? null,
            'purchase_price' => $assetData['purchase_price'] ?? null,
            'depreciation' => $assetData['depreciation'] ?? null,
            'location_id' => $location?->id ?? null,
            'department_id' => $department?->id,
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'warranty' => $assetData['warranty'] ?? null,
            'insurance' => $assetData['insurance'] ?? null,
            'health_score' => $assetData['health_score'] ?? 100,
            'status' => $assetData['status'] ?? 'active',
        ]);

        // Handle tags if provided
        if (!empty($assetData['tags']) && is_array($assetData['tags'])) {
            $tagIds = [];
            foreach ($assetData['tags'] as $tagName) {
                $tag = AssetTag::firstOrCreate(
                    ['name' => $tagName],
                    ['company_id' => $user->company_id]
                );
                $tagIds[] = $tag->id;
            }
            $asset->tags()->attach($tagIds);
        }

        // Generate QR code
        try {
            $qrPath = $this->qrCodeService->generateAssetQRCode($asset);
            if ($qrPath) {
                $asset->qr_code_path = $qrPath;
                $asset->save();
            }
        } catch (\Exception $e) {
            Log::warning("Failed to generate QR code for asset {$asset->id}: " . $e->getMessage());
        }

        // Log activity
        $asset->activities()->create([
            'user_id' => $user->id,
            'action' => 'created',
            'before' => null,
            'after' => $asset->toArray(),
            'comment' => 'Asset imported via bulk import job',
        ]);

        return $asset;
    }

    /**
     * Process location hierarchy (Building -> Location -> Floor)
     */
    protected function processLocationHierarchy(array $assetData, User $user): ?Location
    {
        $location = null;
        $parentLocation = null;

        // If building is provided, create or find building location
        if (!empty($assetData['building'])) {
            // Get default building location type
            $buildingLocationType = LocationType::where('hierarchy_level', 1)
                ->where('name', 'Office Building')
                ->first();
            
            if (!$buildingLocationType) {
                // Fallback to any level 1 location type
                $buildingLocationType = LocationType::where('hierarchy_level', 1)->first();
            }

            $parentLocation = Location::firstOrCreate(
                [
                    'name' => $assetData['building'],
                    'company_id' => $user->company_id,
                    'parent_id' => null, // Building is top level
                ],
                [
                    'user_id' => $user->id,
                    'description' => 'Building: ' . $assetData['building'],
                    'qr_code_path' => null,
                    'address' => null,
                    'location_type_id' => $buildingLocationType?->id ?? 1,
                    'hierarchy_level' => 1
                ]
            );
        }

        // If location is provided, create or find location under building
        if (!empty($assetData['location'])) {
            // Get location type for rooms/areas (hierarchy level 3)
            $roomLocationType = LocationType::where('hierarchy_level', 3)
                ->where('name', 'Office')
                ->first();
                
            if (!$roomLocationType) {
                // Fallback to any level 3 location type
                $roomLocationType = LocationType::where('hierarchy_level', 3)->first();
            }

            $location = Location::firstOrCreate(
                [
                    'name' => $assetData['location'],
                    'company_id' => $user->company_id,
                    'parent_id' => $parentLocation ? $parentLocation->id : null,
                ],
                [
                    'user_id' => $user->id,
                    'description' => 'Location: ' . $assetData['location'],
                    'qr_code_path' => null,
                    'address' => null,
                    'location_type_id' => $roomLocationType?->id ?? 1,
                    'hierarchy_level' => $parentLocation ? 3 : 2
                ]
            );

            // Update parent location reference
            $parentLocation = $location;
        }

        // If floor is provided, create or find floor under location
        if (!empty($assetData['floor'])) {
            // Get floor location type (hierarchy level 2)
            $floorLocationType = LocationType::where('hierarchy_level', 2)
                ->where('name', 'Floor')
                ->first();
                
            if (!$floorLocationType) {
                // Fallback to any level 2 location type
                $floorLocationType = LocationType::where('hierarchy_level', 2)->first();
            }

            $location = Location::firstOrCreate(
                [
                    'name' => $assetData['floor'],
                    'company_id' => $user->company_id,
                    'parent_id' => $parentLocation ? $parentLocation->id : null,
                ],
                [
                    'user_id' => $user->id,
                    'description' => 'Floor: ' . $assetData['floor'],
                    'qr_code_path' => null,
                    'address' => null,
                    'location_type_id' => $floorLocationType?->id ?? 1,
                    'hierarchy_level' => 2
                ]
            );
        }

        return $location;
    }

    /**
     * Safely restore memory limit without causing errors
     */
    private function safeRestoreMemoryLimit(string $originalLimit): void
    {
        try {
            $currentUsage = memory_get_usage(true);
            $originalBytes = $this->parseMemoryLimit($originalLimit);
            
            // Only restore if we have enough headroom (current usage + 50MB buffer)
            $bufferBytes = 50 * 1024 * 1024; // 50MB buffer
            $requiredBytes = $currentUsage + $bufferBytes;
            
            if ($originalBytes >= $requiredBytes) {
                ini_set('memory_limit', $originalLimit);
            } else {
                // Keep a safe memory limit that accommodates current usage
                $safeLimit = max($originalBytes, $requiredBytes);
                $safeLimitMB = ceil($safeLimit / (1024 * 1024));
                ini_set('memory_limit', $safeLimitMB . 'M');
            }
        } catch (\Exception $e) {
            // If anything fails, just leave the current memory limit as is
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int) $limit;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Bulk asset import job failed: {$this->importJob->job_id}. Exception: " . $exception->getMessage());
        $this->importJob->markAsFailed($exception->getMessage());
    }
}