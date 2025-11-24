<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetImage;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AssetImageSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (AssetImage::count() >= 10) {
            $this->command->info('Asset images already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding asset images...');

        $assets = Asset::where('company_id', $company->id)->get();

        if ($assets->isEmpty()) {
            $this->command->warn('No assets found. Run AssetSeeder first.');
            return;
        }

        $captions = [
            'Front view', 'Side view', 'Back view', 'Top view', 'Close-up view',
            'Serial number plate', 'Condition photo', 'Installation location',
            'Control panel', 'Display screen', 'Power connection', 'Maintenance access',
            'Model label', 'Warranty sticker', 'Component detail'
        ];

        $createdCount = 0;

        // Create 1-3 images per asset (ensure we get 10+ total)
        // Using placeholder image service URLs for testing
        foreach ($assets as $asset) {
            $imageCount = rand(1, 3);
            
            for ($i = 0; $i < $imageCount; $i++) {
                // Use placeholder image service (will display actual images)
                $width = fake()->randomElement([600, 800]);
                $height = fake()->randomElement([400, 600]);
                $colors = ['4F46E5', '0EA5E9', '10B981', 'F59E0B', 'EF4444'];
                $color = fake()->randomElement($colors);
                $imageUrl = "https://via.placeholder.com/{$width}x{$height}/{$color}/FFFFFF?text=" . urlencode($asset->name);
                
                AssetImage::create([
                    'asset_id' => $asset->id,
                    'image_path' => $imageUrl,
                    'caption' => fake()->randomElement($captions),
                ]);
                $createdCount++;
                
                if ($createdCount >= 25) {
                    break 2; // Exit both loops once we have 25 images
                }
            }
        }

        $this->command->info("Created {$createdCount} asset images.");
    }
}
