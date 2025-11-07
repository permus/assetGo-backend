<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsAuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingsController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Update company currency (admin-only: super_admin, company_admin, or owner)
     */
    public function updateCurrency(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company found for this user'], 404);
        }

        if (!$this->canManageCompanySettings($user->user_type, $company->owner_id, $user->id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldCurrency = $company->currency;
        $company->currency = strtoupper($request->currency);
        $company->save();

        // Log currency change
        app(SettingsAuditService::class)->logCurrencyChange(
            $oldCurrency,
            $company->currency,
            $user->id,
            $request->ip()
        );

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $company->id,
                [
                    'type' => 'settings',
                    'action' => 'update_currency',
                    'title' => 'Currency Updated',
                    'message' => $this->notificationService->formatSettingsMessage('update_currency'),
                    'data' => [
                        'oldCurrency' => $oldCurrency,
                        'newCurrency' => $company->currency,
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
            \Log::warning('Failed to send currency update notifications', [
                'company_id' => $company->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Currency updated',
            'data' => [
                'company' => $company->fresh(),
            ],
        ]);
    }

    /**
     * Upload/update company logo (admin-only)
     * file field: logo (image)
     */
    public function uploadLogo(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company found for this user'], 404);
        }

        if (!$this->canManageCompanySettings($user->user_type, $company->owner_id, $user->id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('logo');
        $ext = $file->getClientOriginalExtension();
        $path = 'asset-images/company-logos/' . $company->id . '-logo.' . $ext;

        // Store on public disk
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $publicUrl = '/storage/' . $path;

        // Reuse 'logo' column for URL/path
        $company->logo = $publicUrl;
        $company->save();

        // Log logo upload
        app(SettingsAuditService::class)->logLogoUpload($publicUrl, $user->id, $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Logo updated',
            'data' => [
                'logo_url' => $publicUrl,
                'company' => $company->fresh(),
            ],
        ]);
    }

    private function canManageCompanySettings(string $userType, $ownerId, $userId): bool
    {
        if ($ownerId == $userId) {
            return true;
        }

        // Map legacy user_type to admin-like permissions
        $adminTypes = ['admin', 'super_admin', 'company_admin'];
        return in_array($userType, $adminTypes, true);
    }
}


