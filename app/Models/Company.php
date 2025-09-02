<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'subscription_status',
        'subscription_expires_at',
        'currency',
        'settings',
        'business_type',
        'industry',
        'phone',
        'email',
        'address',
        'logo',
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'deleted_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Get the owner of the company
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all users belonging to this company
     */
    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }

    /**
     * Get all roles belonging to this company
     */
    public function roles()
    {
        return $this->hasMany(Role::class);
    }


}