<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
    ];

    /**
     * Get the assets for this type.
     */
    public function assets()
    {
        return $this->hasMany(Asset::class, 'type', 'name');
    }

    /**
     * Get the display name with icon.
     */
    public function getDisplayNameAttribute()
    {
        return $this->name;
    }

    /**
     * Scope to get active asset types.
     */
    public function scopeActive($query)
    {
        return $query;
    }
} 