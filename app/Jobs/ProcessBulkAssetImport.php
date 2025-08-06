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
use App\Models\User;
use App\Services\QRCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkAssetImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;

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
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting bulk asset import job: {$this->importJob->job_id}");

            $this->importJob->status = 'processing';
            $this->importJob->started_at = now();
            $this->importJob->save();

            $user = $this->importJob->user;
            $assetsData = $this->importJob->import_data;
            $errors = [];
            $successful = 0;
            $failed = 0;
            $processed = 0;

            // Process assets in batches to avoid memory issues
            $batchSize = 50;
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
                    
                    // Update progress every 10 assets or at the end
                    if ($processed % 10 === 0 || $processed === $totalAssets) {
                        $this->importJob->updateProgress($processed, $successful, $failed, $errors);
                    }
                }
                
                // Small delay between batches to prevent overwhelming the system
                usleep(100000); // 0.1 seconds
            }

            $this->importJob->markAsCompleted();
            
            Log::info("Completed bulk asset import job: {$this->importJob->job_id}. Success: {$successful}, Failed: {$failed}");

        } catch (\Exception $e) {
            Log::error("Bulk asset import job failed: {$this->importJob->job_id}. Error: " . $e->getMessage());
            $this->importJob->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single asset
     */
    protected function processAsset(array $assetData, User $user): ?Asset
    {
        // Generate unique asset ID
        $assetId = $assetData['asset_id'] ?? null;
        if (!$assetId) {
            $assetId = 'AST-' . strtoupper(substr(uniqid(), -8));
            
            // Ensure uniqueness
            while (Asset::where('asset_id', $assetId)->where('company_id', $user->company_id)->exists()) {
                $assetId = 'AST-' . strtoupper(substr(uniqid(), -8));
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
                    'location_type_id' => null,
                    'hierarchy_level' => 0
                ]
            );
        }

        // If location is provided, create or find location under building
        if (!empty($assetData['location'])) {
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
                    'location_type_id' => null,
                    'hierarchy_level' => 1
                ]
            );

            // Update parent location reference
            $parentLocation = $location;
        }

        // If floor is provided, create or find floor under location
        if (!empty($assetData['floor'])) {
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
                    'location_type_id' => null,
                    'hierarchy_level' => 2
                ]
            );
        }

        return $location;
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