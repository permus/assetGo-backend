<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\ChangeUserPasswordRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Models\Permission;
use App\Notifications\AccountSuspendedNotification;
use App\Mail\AdminCreatedUserMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminUsersController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with('company');

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
    }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        // Load company with all fields and roles
        $user->load('company', 'roles');
        
        // Generate full logo URL if logo exists
        if ($user->company && $user->company->logo) {
            $logoPath = str_replace('/storage/', '', $user->company->logo);
            $logoUrl = Storage::disk('public')->url($logoPath);
            $user->company->setAttribute('logo_url', $logoUrl);
        }
        
        // Add creator information
        if ($user->created_by) {
            // Check if created_by is an admin (from Admin table) or a user
            // Since admin uses separate table, if created_by exists in users table,
            // we'll check if it's an admin user type, otherwise it's an admin
            $creator = User::find($user->created_by);
            if ($creator) {
                // Check if creator is admin type
                $adminTypes = ['admin', 'super_admin', 'company_admin'];
                if (in_array($creator->user_type, $adminTypes)) {
                    $user->setAttribute('created_by_name', 'Administrator');
                } else {
                    $user->setAttribute('created_by_name', $creator->first_name . ' ' . $creator->last_name);
                }
            } else {
                // If not found in users table, it's likely created by an admin from Admin table
                $user->setAttribute('created_by_name', 'Administrator');
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ]
        ]);
    }

    /**
     * Get paginated teams created by the specified user.
     */
    public function getCreatedTeams(User $user, Request $request)
    {
        $query = User::where('created_by', $user->id)
            ->where('user_type', 'team')
            ->with(['roles' => function($query) {
                $query->limit(1); // Only get first role as roles[0]
            }]);

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $teams = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'teams' => $teams->items(),
                'pagination' => [
                    'current_page' => $teams->currentPage(),
                    'last_page' => $teams->lastPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total(),
                    'from' => $teams->firstItem(),
                    'to' => $teams->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'active' => 'sometimes|boolean',
            'user_type' => 'sometimes|string',
        ]);

        // Store original active status to detect suspension
        $wasActive = $user->active;
        $data = $request->only(['first_name', 'last_name', 'email', 'active', 'user_type']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Check if user is being suspended (active changed from true to false)
        $isBeingSuspended = isset($data['active']) && $wasActive && !$data['active'];

        $user->update($data);

        // Handle suspension: revoke tokens and send email
        if ($isBeingSuspended) {
            // Revoke all user tokens to log them out
            $user->tokens()->delete();

            // Send suspension email notification
            try {
                $user->notify(new AccountSuspendedNotification());
            } catch (\Exception $e) {
                // Log error but don't fail the update
                \Log::error('Failed to send account suspension email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'user' => $user->fresh()->load('company'),
            ]
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Store a newly created user with company.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => 'owner',
                'created_by' => $request->user()->id, // Admin creating the user
                'email_verified_at' => now(),
                'active' => true,
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

            // Handle logo upload if provided
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $ext = $file->getClientOriginalExtension();
                $logoPath = 'asset-images/company-logos/temp-' . Str::random(10) . '.' . $ext;
                Storage::disk('public')->put($logoPath, file_get_contents($file->getRealPath()));
            }

            // Create company with user as owner
            $company = Company::create([
                'name' => $request->company_name,
                'slug' => $request->company_name ? Str::slug($request->company_name) : null,
                'owner_id' => $user->id,
                'subscription_status' => 'trial',
                'industry' => $request->industry,
                'business_type' => $request->business_type,
                'email' => $request->company_email,
                'phone' => $request->company_phone,
                'address' => $request->company_address,
                'logo' => $logoPath ? '/storage/' . $logoPath : null,
            ]);

            // Update logo path with company ID if logo was uploaded
            if ($logoPath && $company->id) {
                $newLogoPath = 'asset-images/company-logos/' . $company->id . '-logo.' . pathinfo($logoPath, PATHINFO_EXTENSION);
                if (Storage::disk('public')->exists($logoPath)) {
                    Storage::disk('public')->move($logoPath, $newLogoPath);
                    $company->logo = '/storage/' . $newLogoPath;
                    $company->save();
                }
            }

            // Update user with company_id
            $user->update(['company_id' => $company->id]);

            // Create default roles for the company
            $this->createDefaultRoles($company);

            // Enable all modules for the new company
            $this->enableAllModulesForCompany($company);

            DB::commit();

            // Send welcome email to the newly created user
            try {
                Mail::to($user->email)->send(new AdminCreatedUserMail($user, $request->password));
                \Log::info('Admin created user email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send admin created user email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the request if email fails
            }

            return response()->json([
                'success' => true,
                'message' => 'User and company created successfully',
                'data' => [
                    'user' => $user->fresh()->load('company'),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Admin user creation failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during user creation'
            ], 500);
        }
    }

    /**
     * Change user password (admin override, no current password required).
     */
    public function changePassword(ChangeUserPasswordRequest $request, User $user)
    {
        try {
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Revoke all user tokens to force re-login with new password
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'data' => [
                    'user' => $user->fresh(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Admin password change failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
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
     * Enable all modules for a newly created company
     */
    private function enableAllModulesForCompany(Company $company): void
    {
        // Get all module definitions
        $modules = \App\Models\ModuleDefinition::all();

        foreach ($modules as $module) {
            // Enable all modules for the new company
            \App\Models\CompanyModule::create([
                'company_id' => $company->id,
                'module_id' => $module->id,
                'is_enabled' => true,
            ]);
        }
    }
}
