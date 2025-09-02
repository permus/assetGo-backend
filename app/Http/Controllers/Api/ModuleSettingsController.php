<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyModule;
use App\Models\ModuleDefinition;
use Illuminate\Http\Request;

class ModuleSettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company found for this user'], 404);
        }

        $modules = ModuleDefinition::orderBy('is_system_module', 'desc')
            ->orderBy('sort_order')
            ->get();

        $enabled = CompanyModule::where('company_id', $company->id)
            ->where('is_enabled', true)
            ->pluck('is_enabled', 'module_id');

        $data = $modules->map(function (ModuleDefinition $m) use ($enabled) {
            return [
                'id' => $m->id,
                'key' => $m->key,
                'display_name' => $m->display_name,
                'description' => $m->description,
                'icon_name' => $m->icon_name,
                'route_path' => $m->route_path,
                'sort_order' => $m->sort_order,
                'is_system_module' => (bool) $m->is_system_module,
                'is_enabled' => (bool) ($enabled[$m->id] ?? false) || (bool) $m->is_system_module,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => ['modules' => $data]]);
    }

    public function enable(Request $request, ModuleDefinition $module)
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company found for this user'], 404);
        }
        if (!$this->canManageCompanySettings($user->user_type, $company->owner_id, $user->id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        CompanyModule::updateOrCreate(
            ['company_id' => $company->id, 'module_id' => $module->id],
            ['is_enabled' => true]
        );

        // Optionally initialize permissions here via service
        // app(\App\Services\ModuleService::class)->initializeUserModulePermissions($company, $module);

        return response()->json(['success' => true, 'message' => 'Module enabled']);
    }

    public function disable(Request $request, ModuleDefinition $module)
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company found for this user'], 404);
        }
        if (!$this->canManageCompanySettings($user->user_type, $company->owner_id, $user->id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($module->is_system_module) {
            return response()->json(['success' => false, 'message' => 'System modules cannot be disabled'], 400);
        }

        CompanyModule::updateOrCreate(
            ['company_id' => $company->id, 'module_id' => $module->id],
            ['is_enabled' => false]
        );

        return response()->json(['success' => true, 'message' => 'Module disabled']);
    }

    private function canManageCompanySettings(string $userType, $ownerId, $userId): bool
    {
        if ($ownerId == $userId) {
            return true;
        }
        $adminTypes = ['super_admin', 'company_admin'];
        return in_array($userType, $adminTypes, true);
    }
}


