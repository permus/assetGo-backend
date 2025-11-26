<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Carbon\Carbon;

class AssetReportTestSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Please run CompanySeeder first.');
            return;
        }

        $this->command->info('Seeding comprehensive asset test data for reports...');

        $faker = Faker::create();
        $users = User::where('company_id', $company->id)->get();
        $locations = Location::where('company_id', $company->id)->get();
        $departments = Department::where('company_id', $company->id)->get();
        $categories = AssetCategory::all();
        
        // Get asset statuses
        $statuses = AssetStatus::all()->keyBy('name');
        $activeStatusId = $statuses->get('Active')?->id ?? 1;
        $maintenanceStatusId = $statuses->get('Maintenance')?->id ?? 2;
        $inactiveStatusId = $statuses->get('Inactive')?->id ?? 3;
        $retiredStatusId = $statuses->get('Retired')?->id ?? 4;
        $availableStatusId = $statuses->get('Pending')?->id ?? $activeStatusId;
        
        // Find the next available asset_id counter
        $existingAssets = Asset::where('company_id', $company->id)
            ->where('asset_id', 'LIKE', 'AST-%')
            ->get();
        
        $maxCounter = 0;
        foreach ($existingAssets as $asset) {
            // Extract numeric part from asset_id (e.g., "AST-000001" -> 1)
            if (preg_match('/AST-(\d+)/', $asset->asset_id, $matches)) {
                $counter = (int) $matches[1];
                if ($counter > $maxCounter) {
                    $maxCounter = $counter;
                }
            }
        }
        
        // Start counter from next available number
        $counter = $maxCounter + 1;

        // Diverse asset templates for comprehensive testing
        $assetTemplates = [
            // IT Equipment
            ['name' => 'Dell Latitude Laptop', 'manufacturer' => 'Dell', 'model' => 'Latitude 5520', 'price_range' => [800, 1500]],
            ['name' => 'HP EliteBook', 'manufacturer' => 'HP', 'model' => 'EliteBook 850', 'price_range' => [900, 1600]],
            ['name' => 'MacBook Pro 16"', 'manufacturer' => 'Apple', 'model' => 'MacBook Pro M2', 'price_range' => [2000, 3500]],
            ['name' => 'Lenovo ThinkPad', 'manufacturer' => 'Lenovo', 'model' => 'ThinkPad X1 Carbon', 'price_range' => [1200, 2000]],
            ['name' => 'Dell OptiPlex Desktop', 'manufacturer' => 'Dell', 'model' => 'OptiPlex 7090', 'price_range' => [600, 1200]],
            ['name' => 'HP ProDesk', 'manufacturer' => 'HP', 'model' => 'ProDesk 600', 'price_range' => [500, 1000]],
            ['name' => 'Dell Server R740', 'manufacturer' => 'Dell', 'model' => 'PowerEdge R740', 'price_range' => [8000, 15000]],
            ['name' => 'HP Server DL380', 'manufacturer' => 'HP', 'model' => 'ProLiant DL380', 'price_range' => [7000, 14000]],
            ['name' => 'Samsung Monitor 27"', 'manufacturer' => 'Samsung', 'model' => 'S27R750', 'price_range' => [300, 600]],
            ['name' => 'Dell Monitor 24"', 'manufacturer' => 'Dell', 'model' => 'UltraSharp U2421E', 'price_range' => [250, 500]],
            ['name' => 'HP LaserJet Printer', 'manufacturer' => 'HP', 'model' => 'LaserJet Pro M404', 'price_range' => [200, 400]],
            ['name' => 'Canon Printer', 'manufacturer' => 'Canon', 'model' => 'PIXMA TR8620', 'price_range' => [150, 300]],
            
            // Mobile Devices
            ['name' => 'iPhone 14 Pro', 'manufacturer' => 'Apple', 'model' => 'iPhone 14 Pro', 'price_range' => [900, 1200]],
            ['name' => 'Samsung Galaxy S23', 'manufacturer' => 'Samsung', 'model' => 'Galaxy S23 Ultra', 'price_range' => [800, 1100]],
            ['name' => 'iPad Pro', 'manufacturer' => 'Apple', 'model' => 'iPad Pro 12.9"', 'price_range' => [1000, 1500]],
            ['name' => 'Samsung Tablet', 'manufacturer' => 'Samsung', 'model' => 'Galaxy Tab S8', 'price_range' => [600, 900]],
            
            // Network Equipment
            ['name' => 'Cisco Switch', 'manufacturer' => 'Cisco', 'model' => 'Catalyst 2960-X', 'price_range' => [1000, 3000]],
            ['name' => 'Netgear Router', 'manufacturer' => 'Netgear', 'model' => 'Nighthawk AX12', 'price_range' => [300, 600]],
            ['name' => 'Ubiquiti Access Point', 'manufacturer' => 'Ubiquiti', 'model' => 'UniFi AP AC Pro', 'price_range' => [150, 300]],
            
            // Office Furniture
            ['name' => 'Herman Miller Aeron Chair', 'manufacturer' => 'Herman Miller', 'model' => 'Aeron Size B', 'price_range' => [800, 1200]],
            ['name' => 'Steelcase Gesture Chair', 'manufacturer' => 'Steelcase', 'model' => 'Gesture', 'price_range' => [900, 1300]],
            ['name' => 'Ergonomic Standing Desk', 'manufacturer' => 'Steelcase', 'model' => 'Series 7', 'price_range' => [1200, 2000]],
            ['name' => 'IKEA Desk', 'manufacturer' => 'IKEA', 'model' => 'BEKANT', 'price_range' => [200, 400]],
            ['name' => 'Filing Cabinet', 'manufacturer' => 'HON', 'model' => '4-Drawer', 'price_range' => [300, 600]],
            
            // Machinery/Equipment
            ['name' => 'Industrial Air Compressor', 'manufacturer' => 'Ingersoll Rand', 'model' => 'SS5L5', 'price_range' => [5000, 10000]],
            ['name' => 'Forklift', 'manufacturer' => 'Toyota', 'model' => '8FGCU25', 'price_range' => [20000, 35000]],
            ['name' => 'Generator', 'manufacturer' => 'Generac', 'model' => 'GP8000E', 'price_range' => [800, 1500]],
        ];

        $now = Carbon::now();
        
        // Create 85 assets with comprehensive variety
        $assetsToCreate = 85;
        $created = 0;

        foreach ($assetTemplates as $template) {
            // Create 2-4 assets per template
            $assetsPerTemplate = rand(2, 4);
            
            for ($i = 0; $i < $assetsPerTemplate && $created < $assetsToCreate; $i++) {
                // Purchase dates spread across last 5 years
                $purchaseDateOptions = [
                    $faker->dateTimeBetween('-5 years', '-3 years'), // Older assets
                    $faker->dateTimeBetween('-3 years', '-1 year'),  // Mid-range
                    $faker->dateTimeBetween('-1 year', '-3 months'),  // Recent
                    $faker->dateTimeBetween('-3 months', 'now'),     // Very recent
                ];
                $purchaseDate = Carbon::parse($faker->randomElement($purchaseDateOptions));
                
                // Purchase price within template range
                $purchasePrice = $faker->randomFloat(2, $template['price_range'][0], $template['price_range'][1]);
                
                // Some assets without purchase price (10% chance)
                if (rand(1, 10) === 1) {
                    $purchasePrice = null;
                }
                
                // Depreciation life (24, 36, 48, or 60 months)
                $depreciationLife = $faker->randomElement([24, 36, 48, 60]);
                
                // Calculate realistic depreciation based on purchase date
                $depreciation = null;
                if ($purchasePrice) {
                    $monthsElapsed = $purchaseDate->diffInMonths($now);
                    $monthlyDepreciation = $purchasePrice / $depreciationLife;
                    $accumulatedDepreciation = min($monthlyDepreciation * $monthsElapsed, $purchasePrice);
                    $depreciation = round($accumulatedDepreciation, 2);
                }
                
                // Warranty statuses
                $warranty = null;
                $warrantyType = rand(1, 10);
                
                if ($warrantyType <= 8) { // 80% have warranty
                    $warrantyDuration = $faker->randomElement([365, 730, 1095]); // 1, 2, or 3 years in days
                    $warrantyEndDate = $purchaseDate->copy()->addDays($warrantyDuration);
                    
                    // Create different warranty statuses
                    $warrantyStatus = rand(1, 10);
                    if ($warrantyStatus <= 6) {
                        // Active warranty (60% - expiring 60+ days from now)
                        $warrantyEndDate = $now->copy()->addDays(rand(60, 365));
                        $warranty = $warrantyEndDate->format('Y-m-d');
                    } elseif ($warrantyStatus <= 8) {
                        // Expiring soon (20% - within 30 days)
                        $warrantyEndDate = $now->copy()->addDays(rand(1, 30));
                        $warranty = $warrantyEndDate->format('Y-m-d');
                    } else {
                        // Expired warranty (20% - expired 1-12 months ago)
                        $warrantyEndDate = $now->copy()->subDays(rand(30, 365));
                        $warranty = $warrantyEndDate->format('Y-m-d');
                    }
                }
                // 20% have no warranty (null)
                
                // Asset statuses distribution
                $statusRoll = rand(1, 10);
                if ($statusRoll <= 6) {
                    $statusId = $activeStatusId; // 60% Active
                } elseif ($statusRoll <= 8) {
                    $statusId = $maintenanceStatusId; // 20% Maintenance
                } elseif ($statusRoll <= 9) {
                    $statusId = $inactiveStatusId; // 10% Inactive
                } else {
                    $statusId = $retiredStatusId; // 10% Retired
                }
                
                // Health scores
                $healthScoreRoll = rand(1, 10);
                if ($healthScoreRoll <= 5) {
                    $healthScore = $faker->randomFloat(2, 80, 100); // Healthy (50%)
                } elseif ($healthScoreRoll <= 8) {
                    $healthScore = $faker->randomFloat(2, 60, 79); // Moderate (30%)
                } else {
                    $healthScore = $faker->randomFloat(2, 40, 59); // Poor (20%)
                }
                
                // Location and department (some null for testing)
                $locationId = null;
                $departmentId = null;
                if ($locations->count() > 0 && rand(1, 10) <= 9) {
                    $locationId = $locations->random()->id; // 90% have location
                }
                if ($departments->count() > 0 && rand(1, 10) <= 8) {
                    $departmentId = $departments->random()->id; // 80% have department
                }
                
                // User assignment (70% assigned)
                $userId = null;
                if ($users->count() > 0 && rand(1, 10) <= 7) {
                    $userId = $users->random()->id;
                }
                
                Asset::create([
                    'asset_id' => 'AST-' . str_pad($counter, 6, '0', STR_PAD_LEFT),
                    'name' => $template['name'] . ' #' . ($i + 1),
                    'description' => $faker->sentence(),
                    'category_id' => $categories->count() > 0 ? $categories->random()->id : null,
                    'serial_number' => strtoupper($faker->bothify('SN-????-####')),
                    'model' => $template['model'],
                    'manufacturer' => $template['manufacturer'],
                    'brand' => $template['manufacturer'],
                    'purchase_date' => $purchaseDate,
                    'purchase_price' => $purchasePrice,
                    'depreciation' => $depreciation,
                    'depreciation_life' => $depreciationLife,
                    'location_id' => $locationId,
                    'department_id' => $departmentId,
                    'user_id' => $userId,
                    'company_id' => $company->id,
                    'warranty' => $warranty,
                    'health_score' => $healthScore,
                    'status' => $statusId,
                    'is_active' => 1,
                ]);
                
                $counter++;
                $created++;
            }
        }

        // Create a few additional assets with specific test scenarios
        // Fully depreciated asset
        if ($created < $assetsToCreate) {
            $oldPurchaseDate = Carbon::now()->subYears(6);
            Asset::create([
                'asset_id' => 'AST-' . str_pad($counter, 6, '0', STR_PAD_LEFT),
                'name' => 'Fully Depreciated Test Asset',
                'description' => 'Test asset for fully depreciated scenario',
                'category_id' => $categories->count() > 0 ? $categories->random()->id : null,
                'serial_number' => strtoupper($faker->bothify('SN-DEP-####')),
                'model' => 'Test Model',
                'manufacturer' => 'Test Manufacturer',
                'brand' => 'Test Brand',
                'purchase_date' => $oldPurchaseDate,
                'purchase_price' => 5000,
                'depreciation' => 5000, // Fully depreciated
                'depreciation_life' => 60,
                'location_id' => $locations->count() > 0 ? $locations->random()->id : null,
                'department_id' => $departments->count() > 0 ? $departments->random()->id : null,
                'user_id' => $users->count() > 0 ? $users->random()->id : null,
                'company_id' => $company->id,
                'warranty' => null, // No warranty
                'health_score' => 45.00,
                'status' => $retiredStatusId,
                'is_active' => 1,
            ]);
            $counter++;
            $created++;
        }
        
        // New asset with minimal depreciation
        if ($created < $assetsToCreate) {
            $recentPurchaseDate = Carbon::now()->subMonths(2);
            Asset::create([
                'asset_id' => 'AST-' . str_pad($counter, 6, '0', STR_PAD_LEFT),
                'name' => 'New Asset Minimal Depreciation',
                'description' => 'Test asset for new purchase scenario',
                'category_id' => $categories->count() > 0 ? $categories->random()->id : null,
                'serial_number' => strtoupper($faker->bothify('SN-NEW-####')),
                'model' => 'New Model',
                'manufacturer' => 'New Manufacturer',
                'brand' => 'New Brand',
                'purchase_date' => $recentPurchaseDate,
                'purchase_price' => 10000,
                'depreciation' => round(10000 / 60 * 2, 2), // 2 months depreciation
                'depreciation_life' => 60,
                'location_id' => $locations->count() > 0 ? $locations->random()->id : null,
                'department_id' => $departments->count() > 0 ? $departments->random()->id : null,
                'user_id' => $users->count() > 0 ? $users->random()->id : null,
                'company_id' => $company->id,
                'warranty' => Carbon::now()->addDays(365)->format('Y-m-d'), // Active warranty
                'health_score' => 95.00,
                'status' => $activeStatusId,
                'is_active' => 1,
            ]);
            $counter++;
            $created++;
        }

        $totalCreated = Asset::where('company_id', $company->id)->count();
        
        $this->command->info("Created {$created} new assets for report testing.");
        $this->command->info("Total assets for company: {$totalCreated}");
        
        // Display summary statistics
        $this->command->info("\nAsset Summary:");
        $this->command->info("- Active: " . Asset::where('company_id', $company->id)->where('status', $activeStatusId)->count());
        $this->command->info("- Maintenance: " . Asset::where('company_id', $company->id)->where('status', $maintenanceStatusId)->count());
        $this->command->info("- Inactive: " . Asset::where('company_id', $company->id)->where('status', $inactiveStatusId)->count());
        $this->command->info("- Retired: " . Asset::where('company_id', $company->id)->where('status', $retiredStatusId)->count());
        $this->command->info("- With Warranty: " . Asset::where('company_id', $company->id)->whereNotNull('warranty')->count());
        $this->command->info("- With Purchase Price: " . Asset::where('company_id', $company->id)->whereNotNull('purchase_price')->count());
    }
}

