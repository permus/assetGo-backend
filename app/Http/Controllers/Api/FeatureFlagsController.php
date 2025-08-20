<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagsController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->roles()->with('permissions')->first();

        $hasLocationAccess = false;
        if ($role) {
            $hasLocationAccess = $role->has_location_access;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'role_id' => $role?->id,
                'has_location_access' => $hasLocationAccess,
            ],
        ]);
    }
}


