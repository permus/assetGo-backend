<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\TeamInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Get all team members for the authenticated user's company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $teams = $user->company->users()
            ->where('user_type', 'team')
            ->with(['roles.permissions'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $teams
        ]);
    }

    /**
     * Store a newly created team member (invite)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role_id' => 'required|exists:roles,id',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Validate that the role belongs to the user's company
        $role = $user->company->roles()->find($request->role_id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role for this company'
            ], 422);
        }

        // Generate a random password
        $password = Str::random(12);

        // Create the team member (user with user_type = 'team')
        $teamMember = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'user_type' => 'team',
            'company_id' => $user->company_id,
            'created_by' => $user->id,
        ]);

        // Assign the role to the team member
        $teamMember->roles()->attach($request->role_id);

        // Send invitation email
        $this->sendInvitationEmail($teamMember, $password);

        $teamMember->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Team member invited successfully',
            'data' => $teamMember
        ], 201);
    }

    /**
     * Display the specified team member
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->with(['roles.permissions'])
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $teamMember
        ]);
    }

    /**
     * Update the specified team member
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'role_id' => 'sometimes|required|exists:roles,id',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        // Validate that the role belongs to the user's company
        if ($request->has('role_id')) {
            $role = $user->company->roles()->find($request->role_id);
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role for this company'
                ], 422);
            }
        }

        // Update basic info
        $teamMember->update($request->only(['first_name', 'last_name', 'email']));

        // Update role if provided
        if ($request->has('role_id')) {
            $teamMember->roles()->sync([$request->role_id]);
        }

        $teamMember->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Team member updated successfully',
            'data' => $teamMember
        ]);
    }

    /**
     * Remove the specified team member
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        $teamMember->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team member removed successfully'
        ]);
    }

    /**
     * Resend invitation email to team member
     */
    public function resendInvitation(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        // Generate a new password
        $password = Str::random(12);
        $teamMember->update(['password' => Hash::make($password)]);

        // Send invitation email
        $this->sendInvitationEmail($teamMember, $password);

        return response()->json([
            'success' => true,
            'message' => 'Invitation email sent successfully'
        ]);
    }

    /**
     * Get team member statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        $totalTeamMembers = $company->users()->where('user_type', 'team')->count();
        $activeTeamMembers = $company->users()->where('user_type', 'team')->where('email_verified_at', '!=', null)->count();
        $pendingTeamMembers = $company->users()->where('user_type', 'team')->where('email_verified_at', null)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_team_members' => $totalTeamMembers,
                'active_team_members' => $activeTeamMembers,
                'pending_team_members' => $pendingTeamMembers,
            ]
        ]);
    }

    /**
     * Get available roles for the company
     */
    public function getAvailableRoles(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->company->roles()->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail($user, $password)
    {
        try {
            // Send the invitation email
            Mail::to($user->email)->send(new TeamInvitationMail($user, $password));
            
            // Log successful email sending
            \Log::info('Team member invitation email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'company' => $user->company->name,
            ]);
        } catch (\Exception $e) {
            // Log email sending error
            \Log::error('Failed to send team invitation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            
            // You might want to throw an exception here or handle it differently
            // For now, we'll just log the error
        }
    }
} 