<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'hierarchy_level',
        'icon',
        'suggestions',
    ];

    protected $casts = [
        'suggestions' => 'array',
    ];

    /**
     * Get all locations of this type
     */
    public function locations()
    {
        return $this->hasMany(Location::class, 'location_type_id');
    }

    /**
     * Check if this type can be a child of another type
     */
    public function canBeChildOf(LocationType $parentType)
    {
        // Check if this type's hierarchy level is exactly one more than parent's
        return $this->hierarchy_level === ($parentType->hierarchy_level + 1);
    }

    /**
     * Get allowed child types for this location type
     */
    public function getAllowedChildTypes()
    {
        return self::where('hierarchy_level', $this->hierarchy_level + 1)->get();
    }

    /**
     * Get allowed parent types for this location type
     */
    public function getAllowedParentTypes()
    {
        if ($this->hierarchy_level === 0) {
            return collect(); // Top level has no parents
        }
        
        return self::where('hierarchy_level', $this->hierarchy_level - 1)->get();
    }
}