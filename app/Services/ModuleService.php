<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ModuleDefinition;

class ModuleService
{
    /**
     * Initialize default permissions for the module for all users in a company.
     * Idempotent: safe to run multiple times.
     */
    public function initializeUserModulePermissions(Company $company, ModuleDefinition $module): void
    {
        // Placeholder: your role/permission system may already handle this.
        // Hook here to seed or sync defaults when a module is enabled.
    }
}


