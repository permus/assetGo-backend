<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'user_type',
        'email',
        'password',
        'company_id',
        'created_by',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
    ];

    /**
     * Get the company that the user belongs to
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the companies owned by this user
     */
    public function ownedCompanies()
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * Get the user's full name
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the roles that the user has
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($roleName)
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has a specific permission through any of their roles
     */
    public function hasPermission($module, $action)
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($module, $action) {
            $query->whereRaw("JSON_EXTRACT(permissions, '$.{$module}.{$action}') = true");
        })->exists();
    }

    /**
     * Get all permissions for the user through their roles
     */
    public function getAllPermissions()
    {
        $permissions = [];
        
        foreach ($this->roles as $role) {
            if ($role->permissions) {
                $rolePermissions = $role->permissions->permissions;
                foreach ($rolePermissions as $module => $actions) {
                    if (!isset($permissions[$module])) {
                        $permissions[$module] = [];
                    }
                    foreach ($actions as $action => $value) {
                        if ($value === true) {
                            $permissions[$module][$action] = true;
                        }
                    }
                }
            }
        }
        
        return $permissions;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }
}
