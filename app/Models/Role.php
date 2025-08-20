<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'company_id',
    ];

    protected $appends = [
        'has_location_access',
    ];

    /**
     * Get the company that owns the role
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the users that have this role
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Get the permissions for this role
     */
    public function permissions()
    {
        return $this->hasOne(Permission::class);
    }

    /**
     * Check if the role has a specific permission
     */
    public function hasPermission($module, $action)
    {
        if (!$this->permissions) {
            return false;
        }

        $permissions = $this->permissions->permissions;
        
        return isset($permissions[$module][$action]) && $permissions[$module][$action] === true;
    }

    /**
     * Get all permissions for this role
     */
    public function getAllPermissions()
    {
        return $this->permissions ? $this->permissions->permissions : [];
    }

    public function getHasLocationAccessAttribute(): bool
    {
        $permissions = $this->permissions?->permissions ?? [];
        foreach (['can_view','can_create','can_edit','can_delete'] as $key) {
            if (data_get($permissions, "locations.$key", false) === true) {
                return true;
            }
        }
        return false;
    }
} 