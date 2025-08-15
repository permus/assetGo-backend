<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkOrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            ['name' => 'draft', 'slug' => 'draft'],
            ['name' => 'open', 'slug' => 'open'],
            ['name' => 'in progress', 'slug' => 'in-progress'],
            ['name' => 'Completed', 'slug' => 'completed'],
            ['name' => 'On Hold', 'slug' => 'on-hold'],
            ['name' => 'Cancelled', 'slug' => 'cancelled'],
        ];

        foreach ($rows as $i => $r) {
            DB::table('work_order_status')->updateOrInsert(
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
