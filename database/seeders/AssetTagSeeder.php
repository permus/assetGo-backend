<?php

namespace Database\Seeders;

use App\Models\AssetTag;
use Illuminate\Database\Seeder;

class AssetTagSeeder extends Seeder
{
    public function run(): void
    {
        if (AssetTag::count() > 0) return;

        $tags = ['Critical', 'High Priority', 'Maintenance Required', 'Under Warranty', 'New Purchase'];
        
        foreach ($tags as $tagName) {
            AssetTag::create(['name' => $tagName]);
        }
        
        $this->command->info('Created asset tags.');
    }
}
