<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            ['name' => 'low', 'slug' => 'low'],
            ['name' => 'medium', 'slug' => 'medium'],
            ['name' => 'high', 'slug' => 'high'],
            ['name' => 'Critical', 'slug' => 'critical'],
            ['name' => 'PPM', 'slug' => 'ppm'],
        ];

        foreach ($rows as $i => $r) {
            DB::table('work_order_priority')->updateOrInsert(
                ['slug' => $r['slug']],
                [
                    'name' => $r['name'],
                    'company_id' => null,
                    'is_management' => true,
                    'sort' => $i,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
