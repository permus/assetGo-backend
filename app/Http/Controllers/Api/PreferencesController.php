<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PreferencesController extends Controller
{
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
        $preferences = $user->preferences ?? [];
        foreach ($validator->validated() as $k => $v) {
            $preferences[$k] = $v;
        }
        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated',
            'data' => $user->preferences,
        ]);
    }
}


