<?php

namespace Database\Seeders;

use App\Models\ImportMapping;
use App\Models\ImportSession;
use Illuminate\Database\Seeder;

class ImportMappingSeeder extends Seeder
{
    public function run(): void
    {
        if (ImportMapping::count() >= 10) {
            $this->command->info('Import mappings already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding import mappings...');

        $sessions = ImportSession::all();

        if ($sessions->isEmpty()) {
            $this->command->warn('No import sessions found. Run ImportSessionSeeder first.');
            return;
        }

        foreach (range(1, 12) as $index) {
            ImportMapping::factory()->create([
                'import_session_id' => $sessions->random()->id,
            ]);
        }

        $this->command->info('Created 12 import mappings.');
    }
}

