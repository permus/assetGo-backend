<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceChecklistResponse;
use App\Models\ScheduleMaintenanceAssigned;
use App\Models\MaintenancePlanChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MaintenanceChecklistResponseController extends Controller
{
    /**
     * Get all responses for an assigned maintenance
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule_maintenance_assigned_id' => 'required|integer|exists:schedule_maintenance_assigned,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $assignmentId = $request->schedule_maintenance_assigned_id;
        
        // Verify user has access to this assignment
        $assignment = ScheduleMaintenanceAssigned::find($assignmentId);
        if (!$assignment || $assignment->team_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $responses = MaintenanceChecklistResponse::where('schedule_maintenance_assigned_id', $assignmentId)
            ->with('checklistItem')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $responses
        ]);
    }

    /**
     * Save or update a checklist item response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule_maintenance_assigned_id' => 'required|integer|exists:schedule_maintenance_assigned,id',
            'checklist_item_id' => 'required|integer|exists:maintenance_plans_checklists,id',
            'response_type' => 'required|in:checkbox,measurements,text_input,photo_capture,pass_fail',
            'response_value' => 'nullable', // Accept string or array (will be parsed if string)
            'photo' => 'nullable|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Parse response_value if it's a JSON string (from FormData)
        $responseValue = $request->response_value;
        if (is_string($responseValue)) {
            $responseValue = json_decode($responseValue, true);
        }

        $assignmentId = $request->schedule_maintenance_assigned_id;
        
        // Verify user has access to this assignment
        $assignment = ScheduleMaintenanceAssigned::find($assignmentId);
        if (!$assignment || $assignment->team_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Verify checklist item belongs to the plan
        $checklistItem = MaintenancePlanChecklist::find($request->checklist_item_id);
        $schedule = $assignment->schedule()->with('plan')->first();
        if (!$schedule || $schedule->plan->id !== $checklistItem->maintenance_plan_id) {
            return response()->json([
                'success' => false,
                'message' => 'Checklist item does not belong to this maintenance plan'
            ], 422);
        }

        // Handle photo upload if present
        $photoUrl = null;
        if ($request->hasFile('photo') && $request->response_type === 'photo_capture') {
            $file = $request->file('photo');
            $path = $file->store('maintenance-checklist-photos', 'public');
            $photoUrl = Storage::url($path);
        }

        // Find or create response
        $response = MaintenanceChecklistResponse::updateOrCreate(
            [
                'schedule_maintenance_assigned_id' => $assignmentId,
                'checklist_item_id' => $request->checklist_item_id,
            ],
            [
                'user_id' => $request->user()->id,
                'response_type' => $request->response_type,
                'response_value' => $responseValue,
                'photo_url' => $photoUrl ?? $request->photo_url,
                'completed_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $response->load('checklistItem')
        ], 200);
    }

    /**
     * Get a specific response
     */
    public function show(Request $request, $id)
    {
        $response = MaintenanceChecklistResponse::with(['checklistItem', 'user'])->find($id);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Response not found'
            ], 404);
        }

        // Verify user has access
        $assignment = $response->assignment;
        if ($assignment->team_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Update an existing response
     */
    public function update(Request $request, $id)
    {
        $response = MaintenanceChecklistResponse::find($id);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Response not found'
            ], 404);
        }

        // Verify user has access
        $assignment = $response->assignment;
        if ($assignment->team_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'response_value' => 'nullable', // Accept string or array
            'photo' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle photo upload if present
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($response->photo_url) {
                $oldPath = str_replace('/storage/', '', parse_url($response->photo_url, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }
            
            $file = $request->file('photo');
            $path = $file->store('maintenance-checklist-photos', 'public');
            $response->photo_url = Storage::url($path);
        }

        if ($request->has('response_value')) {
            // Parse response_value if it's a JSON string (from FormData)
            $responseValue = $request->response_value;
            if (is_string($responseValue)) {
                $responseValue = json_decode($responseValue, true);
            }
            $response->response_value = $responseValue;
        }

        $response->completed_at = now();
        $response->save();

        return response()->json([
            'success' => true,
            'data' => $response->load('checklistItem')
        ]);
    }

    /**
     * Delete a response
     */
    public function destroy(Request $request, $id)
    {
        $response = MaintenanceChecklistResponse::find($id);

        if (!$response) {
            return response()->json([
                'success' => false,
                'message' => 'Response not found'
            ], 404);
        }

        // Verify user has access
        $assignment = $response->assignment;
        if ($assignment->team_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Delete photo if exists
        if ($response->photo_url) {
            $oldPath = str_replace('/storage/', '', parse_url($response->photo_url, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }

        $response->delete();

        return response()->json([
            'success' => true,
            'message' => 'Response deleted successfully'
        ]);
    }
}

