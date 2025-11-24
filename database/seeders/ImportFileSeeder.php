<?php

namespace Database\Seeders;

use App\Models\ImportFile;
use App\Models\ImportSession;
use Illuminate\Database\Seeder;

class ImportFileSeeder extends Seeder
{
    public function run(): void
    {
        if (ImportFile::count() >= 10) {
            $this->command->info('Import files already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding import files...');

        $sessions = ImportSession::all();

        if ($sessions->isEmpty()) {
            $this->command->warn('No import sessions found. Run ImportSessionSeeder first.');
            return;
        }

        foreach (range(1, 12) as $index) {
            ImportFile::factory()->create([
                'import_session_id' => $sessions->random()->id,
            ]);
        }

        $this->command->info('Created 12 import files.');
    }
}

