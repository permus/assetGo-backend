<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetTransferSeeder extends Seeder
{
    public function run(): void
    {
        $assets = Asset::limit(2)->get();
        $locations = Location::limit(2)->get();
        $users = User::limit(2)->get();

        if ($assets->count() < 2 || $locations->count() < 2 || $users->count() < 2) return;

        foreach ($assets as $asset) {
            AssetTransfer::create([
                'asset_id' => $asset->id,
                'old_location_id' => $locations[0]->id,
                'new_location_id' => $locations[1]->id,
                'from_user_id' => $users[0]->id,
                'to_user_id' => $users[1]->id,
                'reason' => 'Department restructuring',
                'transfer_date' => now()->subDays(rand(1, 30)),
                'status' => 'completed',
                'created_by' => $users[0]->id,
            ]);
        }
        $this->command->info('Created asset transfers.');
    }
}
