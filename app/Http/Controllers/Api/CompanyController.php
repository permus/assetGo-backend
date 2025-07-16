<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    /**
     * Get user's company details
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'No company found for this user'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company->load('owner')
            ]
        ]);
    }

    /**
     * Update company details
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'No company found for this user'
            ], 404);
        }

        // Check if user is the owner or has permission to update
        if ($company->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update company details'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'business_type' => 'string|max:255',
            'industry' => 'string|max:255',
            'phone' => 'string|max:20',
            'email' => 'email|max:255',
            'address' => 'string|max:500',
            'logo' => 'string|max:255',
            'subscription_status' => 'string|in:trial,active,expired,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'name', 'business_type', 'industry', 'phone', 
            'email', 'address', 'logo', 'subscription_status'
        ]);

        // Update slug if name is changed
        if ($request->filled('name')) {
            $updateData['slug'] = Str::slug($request->name);
        }

        $company->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully',
            'data' => [
                'company' => $company->fresh()->load('owner')
            ]
        ]);
    }

    /**
     * Get company users (for company owners/managers)
     */
    public function users(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'No company found for this user'
            ], 404);
        }

        // Check if user has permission to view company users
        if ($company->owner_id !== $user->id && $user->user_type !== 'manager') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view company users'
            ], 403);
        }

        $companyUsers = $company->users()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $companyUsers,
                'total' => $companyUsers->count()
            ]
        ]);
    }
}