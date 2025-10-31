<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SettingsAuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
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

        // Generate full logo URL if logo exists
        $logoUrl = $company->logo ? \Storage::disk('public')->url(str_replace('/storage/', '', $company->logo)) : null;
        $company->setAttribute('logo_url', $logoUrl);
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

        // Check if user is the owner or admin (super_admin/company_admin)
        $isOwner = ($company->owner_id === $user->id);
        $isAdmin = in_array($user->user_type, ['super_admin', 'company_admin'], true);
        if (!$isOwner && !$isAdmin) {
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

        // Log company update
        $auditService = app(SettingsAuditService::class);
        $auditService->logCompanyUpdate(
            $company->toArray(),
            $updateData,
            $user->id,
            $request->ip()
        );

        $company->update($updateData);

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $company->id,
                [
                    'type' => 'settings',
                    'action' => 'update_company_info',
                    'title' => 'Company Information Updated',
                    'message' => $this->notificationService->formatSettingsMessage('update_company_info'),
                    'data' => [
                        'companyId' => $company->id,
                        'companyName' => $company->name,
                        'changes' => $updateData,
                        'createdBy' => [
                            'id' => $user->id,
                            'name' => $user->first_name . ' ' . $user->last_name,
                            'userType' => $user->user_type,
                        ],
                    ],
                    'created_by' => $user->id,
                ],
                $user->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send company info update notifications', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
        }

        $fresh = $company->fresh()->load('owner');
        // Generate full logo URL if logo exists
        $logoUrl = $fresh->logo ? \Storage::disk('public')->url(str_replace('/storage/', '', $fresh->logo)) : null;
        $fresh->setAttribute('logo_url', $logoUrl);
        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully',
            'data' => [
                'company' => $fresh
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