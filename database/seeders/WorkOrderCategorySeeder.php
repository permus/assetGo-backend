<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names = [
            'PPM', 'HVAC System', 'Plumbing System', 'Fire Safety', 'Fixtures',
            'Gargens & landscaping', 'Elevator System', 'Lighting System', 'Electrical System',
            'Security System', 'Camera System', 'Passenger Vehicle', 'Commercial Vehicle',
        ];

        foreach ($names as $i => $name) {
            DB::table('work_order_categories')->updateOrInsert(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'company_id' => null,
                    'sort' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null
                ]
            );
        }
    }
}
