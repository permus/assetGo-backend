<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the role that owns the permission
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Set permissions for a specific module
     */
    public function setModulePermissions($module, $permissions)
    {
        $currentPermissions = $this->permissions ?? [];
        $currentPermissions[$module] = $permissions;
        $this->permissions = $currentPermissions;
        $this->save();
    }

    /**
     * Get permissions for a specific module
     */
    public function getModulePermissions($module)
    {
        return $this->permissions[$module] ?? [];
    }

    /**
     * Check if a specific permission exists
     */
    public function hasPermission($module, $action)
    {
        return isset($this->permissions[$module][$action]) && $this->permissions[$module][$action] === true;
    }
} 