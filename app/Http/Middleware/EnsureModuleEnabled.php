<?php

namespace App\Http\Middleware;

use App\Models\CompanyModule;
use App\Models\ModuleDefinition;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /**
     * Ensure the requested module is enabled for the authenticated user's company.
     * Usage: ->middleware('module:assets')
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $module = ModuleDefinition::where('key', $moduleKey)->first();
        if (!$module) {
            // Unknown module key – deny by default
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        // System modules are always enabled
        if ($module->is_system_module) {
            return $next($request);
        }

        $isEnabled = CompanyModule::where('company_id', $user->company_id)
            ->where('module_id', $module->id)
            ->where('is_enabled', true)
            ->exists();

        if (!$isEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'This module is disabled for your company'
            ], 403);
        }

        return $next($request);
    }
}


