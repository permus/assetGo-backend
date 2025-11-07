<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsAuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreferencesController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function show(Request $request)
    {
        $user = $request->user();
        $prefs = $user->preferences ?? [];

        return response()->json([
            'success' => true,
            'data' => $prefs,
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language' => 'sometimes|string|max:10',
            'rtl' => 'sometimes|boolean',
            'currency' => 'sometimes|string|regex:/^[A-Z]{3}$/',
            'date_format' => 'sometimes|string|max:20',
            'time_format' => 'sometimes|string|max:20',
            'number_format' => 'sometimes|string|max:20',
            'timezone' => 'sometimes|string|max:64',
            // Notification preferences
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'maintenance_alerts' => 'sometimes|boolean',
            'work_order_updates' => 'sometimes|boolean',
            // Display preferences
            'dashboard_layout' => 'sometimes|string|in:grid,list',
            'items_per_page' => 'sometimes|integer|min:5|max:100',
            'auto_refresh' => 'sometimes|boolean',
            'compact_view' => 'sometimes|boolean',
            'show_avatars' => 'sometimes|boolean',
            // Accessibility
            'dark_mode' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $oldPreferences = $user->preferences ?? [];
        $preferences = $oldPreferences;
        $changes = [];
        foreach ($validator->validated() as $k => $v) {
            if (isset($oldPreferences[$k]) && $oldPreferences[$k] != $v) {
                $changes[$k] = ['old' => $oldPreferences[$k], 'new' => $v];
            } elseif (!isset($oldPreferences[$k])) {
                $changes[$k] = ['old' => null, 'new' => $v];
            }
            $preferences[$k] = $v;
        }
        $user->preferences = $preferences;
        $user->save();

        // Log preference update
        app(SettingsAuditService::class)->logPreferenceUpdate(
            $oldPreferences,
            $preferences,
            $user->id,
            $request->ip()
        );

        // Determine which notification action to use based on what changed
        $action = 'update_preferences';
        if (isset($changes['language'])) {
            $action = 'update_language';
        } elseif (isset($changes['rtl'])) {
            $action = 'toggle_rtl';
        } elseif (isset($changes['date_format']) || isset($changes['time_format'])) {
            $action = 'update_date_time_format';
        }

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $user->company_id,
                [
                    'type' => 'settings',
                    'action' => $action,
                    'title' => ucfirst(str_replace('_', ' ', $action)),
                    'message' => $this->notificationService->formatSettingsMessage($action),
                    'data' => [
                        'changes' => $changes,
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
            \Log::warning('Failed to send preferences update notifications', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated',
            'data' => $user->preferences,
        ]);
    }
}


