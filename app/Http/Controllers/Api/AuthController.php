<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Models\Permission;
use App\Http\Middleware\ThrottleLoginAttempts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user and create associated company
     */
    public function register(RegisterRequest $request)
    {
        try {
            DB::beginTransaction();

            // Create user (created_by will be null for self-registration)
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => $request->user_type ?? 'owner',
                'created_by' => null, // Self-registration, no creator
                'preferences' => [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'maintenance_alerts' => true,
                    'work_order_updates' => true,
                    'dashboard_layout' => 'grid',
                    'items_per_page' => 20,
                    'auto_refresh' => false,
                    'compact_view' => false,
                    'show_avatars' => true,
                    'dark_mode' => false,
                ],
            ]);

            // Create company with user as owner
            $company = Company::create([
                'name' => $request->company_name,
                'slug' => $request->company_name ? Str::slug($request->company_name) : null,
                'owner_id' => $user->id,
                'subscription_status' => 'trial',
            ]);

            // Update user with company_id
            $user->update(['company_id' => $company->id, 'email_verified_at' => now()]);

            // Create default roles for the company
            $this->createDefaultRoles($company);

            // Enable all modules for the new company
            $this->enableAllModulesForCompany($company);

            // Send email verification notification
            // $user->sendEmailVerificationNotification();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully. Please Login now.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log detailed error for debugging
            \Log::error('Registration failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during registration'
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            // Increment failed login attempts
            ThrottleLoginAttempts::incrementAttempts($request->email);
            
            // Log failed login attempt
            \Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        
        // Clear failed login attempts on successful login
        ThrottleLoginAttempts::clearAttempts($request->email);
        
        // Log successful login
        \Log::info('Successful login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()
        ]);

        // Email verification check removed - users can login without email verification

        $token = $user->createToken('auth_token')->plainTextToken;

        // Get user permissions based on user type
        $permissions = [];
        $moduleAccess = [];
        
        if ($user->user_type === 'team') {
            // For team users, get permissions from their roles
            $permissions = $user->getAllPermissions();
            
            // Determine module access based on permissions
            $moduleAccess = $this->getModuleAccessFromPermissions($permissions);
        } else {
            // For company users (owners, etc.), return all permissions as true
            $permissions = [
                'assets' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'locations' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'work_orders' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'teams' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'maintenance' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'inventory' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'sensors' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'ai_features' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
                'reports' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_export' => true,
                ],
            ];
            
            // Company users have access to all modules
            $moduleAccess = [
                'dashboard' => true,
                'settings' => true,
                'assets' => true,
                'locations' => true,
                'work_orders' => true,
                'teams' => true,
                'maintenance' => true,
                'inventory' => true,
                'sensors' => true,
                'ai_features' => true,
                'reports' => true,
                'facilities_locations' => true,
                'sla' => true,
                'eservices' => true,
                'tenant_portal' => true,
                'maintenance_requests' => true,
                'amenity_bookings' => true,
                'move_in_out_requests' => true,
                'fitout_requests' => true,
                'inhouse_services' => true,
                'parcel_management' => true,
                'visitor_management' => true,
                'business_directory' => true,
                'tenant_communication' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load(['company', 'roles.permissions']),
                'token' => $token,
                'token_type' => 'Bearer',
                'email_verified' => $user->hasVerifiedEmail(),
                'permissions' => $permissions,
                'module_access' => $moduleAccess
            ]
        ]);
    }

    /**
     * Determine module access based on user permissions
     */
    private function getModuleAccessFromPermissions(array $permissions): array
    {
        $moduleAccess = [
            'dashboard' => true, // Always accessible
            'settings' => false, // Only for company users
        ];

        // Map permission modules to module keys
        $moduleMapping = [
            'assets' => 'assets',
            'locations' => 'locations',
            'work_orders' => 'work_orders',
            'teams' => 'teams',
            'maintenance' => 'maintenance',
            'inventory' => 'inventory',
            'sensors' => 'sensors',
            'ai_features' => 'ai_features',
            'reports' => 'reports',
            'facilities_locations' => 'facilities_locations',
            'sla' => 'sla',
            'eservices' => 'eservices',
            'tenant_portal' => 'tenant_portal',
            'maintenance_requests' => 'maintenance_requests',
            'amenity_bookings' => 'amenity_bookings',
            'move_in_out_requests' => 'move_in_out_requests',
            'fitout_requests' => 'fitout_requests',
            'inhouse_services' => 'inhouse_services',
            'parcel_management' => 'parcel_management',
            'visitor_management' => 'visitor_management',
            'business_directory' => 'business_directory',
            'tenant_communication' => 'tenant_communication',
        ];

        // Check if user has any permission for each module
        foreach ($moduleMapping as $permissionModule => $moduleKey) {
            $hasAccess = false;
            
            if (isset($permissions[$permissionModule])) {
                // Check if user has any permission for this module
                foreach ($permissions[$permissionModule] as $action => $value) {
                    if ($value === true) {
                        $hasAccess = true;
                        break;
                    }
                }
            }
            
            $moduleAccess[$moduleKey] = $hasAccess;
        }

        return $moduleAccess;
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $permissions = [];
        $moduleAccess = [];
        
        if ($user->user_type === 'team') {
            $permissions = $user->getAllPermissions();
            $moduleAccess = $this->getModuleAccessFromPermissions($permissions);
        } else {
            // Company users have access to all modules
            $moduleAccess = [
                'dashboard' => true,
                'settings' => true,
                'assets' => true,
                'locations' => true,
                'work_orders' => true,
                'teams' => true,
                'maintenance' => true,
                'inventory' => true,
                'sensors' => true,
                'ai_features' => true,
                'reports' => true,
                'facilities_locations' => true,
                'sla' => true,
                'eservices' => true,
                'tenant_portal' => true,
                'maintenance_requests' => true,
                'amenity_bookings' => true,
                'move_in_out_requests' => true,
                'fitout_requests' => true,
                'inhouse_services' => true,
                'parcel_management' => true,
                'visitor_management' => true,
                'business_directory' => true,
                'tenant_communication' => true,
            ];
        }
        
        // Add avatar URL to user data
        $user->avatar_url = $user->avatar ? \Storage::disk('public')->url($user->avatar) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load(['company', 'roles.permissions']),
                'permissions' => $permissions,
                'module_access' => $moduleAccess,
                'email_verified' => $user->hasVerifiedEmail()
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $updateData = $request->only(['first_name', 'last_name', 'email']);

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                \Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $updateData['avatar'] = $avatarPath;
        }

        $user->update($updateData);

        // Load the updated user with avatar URL
        $user = $user->fresh();
        $user->avatar_url = $user->avatar ? \Storage::disk('public')->url($user->avatar) : null;

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user->load('company')
            ]
        ]);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $avatarPath]);

        // Return avatar URL
        $avatarUrl = \Storage::disk('public')->url($avatarPath);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar_url' => $avatarUrl,
                'avatar_path' => $avatarPath
            ]
        ]);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }

    /**
     * Send password reset link to user's email
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email address'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send password reset link',
            'error' => __($status)
        ], 400);
    }

    /**
     * Reset user password using token
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Revoke all existing tokens for security
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to reset password',
            'error' => __($status)
        ], 400);
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Optionally revoke all tokens except current one for security
        $currentToken = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $currentToken->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        // Find user by ID
        $user = User::findOrFail($id);

        // Verify the hash matches the user's email
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
                'email_verified' => true
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully! You can now log in.',
            'email_verified' => true
        ]);
    }

    /**
     * Resend email verification (for unverified users)
     */
    public function resendVerification(Request $request)
    {
        // Allow both authenticated and unauthenticated requests
        if ($request->user()) {
            $user = $request->user();
        } else {
            // For unauthenticated requests, require email
            $request->validate(['email' => 'required|email|exists:users,email']);
            $user = User::where('email', $request->email)->first();
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
                'email_verified' => true
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent to your email address'
        ]);
    }

    /**
     * Create default roles for a company
     */
    private function createDefaultRoles(Company $company): void
    {
        $defaultRoles = [
            [
                'name' => 'Admin',
                'description' => 'Full access to all features and settings',
                'permissions' => [
                    'assets' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'locations' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'work_orders' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'teams' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'maintenance' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'inventory' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'sensors' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'ai_features' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'reports' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                ]
            ],
            [
                'name' => 'Technician',
                'description' => 'Can view and edit assets, locations, and work orders',
                'permissions' => [
                    'assets' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => true,
                    ],
                    'locations' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'work_orders' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'teams' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'maintenance' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'inventory' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'sensors' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'ai_features' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'reports' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                ]
            ],
            [
                'name' => 'User',
                'description' => 'Basic access to view assets and locations',
                'permissions' => [
                    'assets' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'locations' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'work_orders' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'teams' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'maintenance' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'inventory' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'sensors' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'ai_features' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'reports' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                ]
            ],
        ];

        foreach ($defaultRoles as $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'company_id' => $company->id,
            ]);

            Permission::create([
                'role_id' => $role->id,
                'permissions' => $roleData['permissions'],
            ]);
        }
    }

    /**
     * Enable all modules for a newly registered company
     */
    private function enableAllModulesForCompany(Company $company): void
    {
        // Get all module definitions
        $modules = \App\Models\ModuleDefinition::all();

        foreach ($modules as $module) {
            // Enable all modules for the new company
            // System modules are always enabled, but we still create the record
            \App\Models\CompanyModule::create([
                'company_id' => $company->id,
                'module_id' => $module->id,
                'is_enabled' => true,
            ]);
        }
    }
}
