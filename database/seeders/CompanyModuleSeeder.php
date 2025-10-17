<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\ModuleDefinition;
use Illuminate\Database\Seeder;

class CompanyModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company || CompanyModule::count() > 0) return;

        $modules = ModuleDefinition::all();

        foreach ($modules as $module) {
            CompanyModule::create([
                'company_id' => $company->id,
                'module_id' => $module->id,
                'is_enabled' => true,
            ]);
        }
        $this->command->info('Created company modules.');
    }
}
