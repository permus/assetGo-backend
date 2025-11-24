<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetActivitySeeder extends Seeder
{
    public function run(): void
    {
        $assets = Asset::limit(2)->get();
        if ($assets->count() == 0) return;

        foreach ($assets as $asset) {
            AssetActivity::create([
                'asset_id' => $asset->id,
                'user_id' => User::where('company_id', $asset->company_id)->first()?->id,
                'action' => 'created',
                'comment' => 'Asset added to system',
            ]);
        }
        
        $this->command->info('Created asset activities.');
    }
}
