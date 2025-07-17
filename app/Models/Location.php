<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'parent_id',
        'location_type_id',
        'name',
        'description',
        'address',
        'slug',
        'qr_code_path',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $with = ['locationType'];

    /**
     * Boot method to generate slug automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($location) {
            if (empty($location->slug)) {
                $location->slug = static::generateUniqueSlug($location->name);
            }
        });

        static::updating(function ($location) {
            if ($location->isDirty('name')) {
                $location->slug = static::generateUniqueSlug($location->name, $location->id);
            }
        });
    }

    /**
     * Generate unique slug
     */
    private static function generateUniqueSlug($name, $excludeId = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->when($excludeId, function ($query, $excludeId) {
            return $query->where('id', '!=', $excludeId);
        })->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the company that owns this location
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the location type
     */
    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    /**
     * Get the parent location
     */
    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * Get all child locations
     */
    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    /**
     * Get all descendants (recursive)
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors (parents up the hierarchy)
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors->reverse();
    }

    /**
     * Get the full hierarchical path
     */
    public function getFullPathAttribute()
    {
        $path = $this->ancestors()->pluck('name')->toArray();
        $path[] = $this->name;
        
        return implode(' → ', $path);
    }

    /**
     * Get the hierarchy level (0 = root, 1 = first level, etc.)
     */
    public function getHierarchyLevelAttribute()
    {
        return $this->ancestors()->count();
    }

    /**
     * Check if location has children
     */
    public function hasChildren()
    {
        return $this->children()->exists();
    }

    /**
     * Get QR code URL
     */
    public function getQrCodeUrlAttribute()
    {
        if ($this->qr_code_path) {
            return asset('storage/' . $this->qr_code_path);
        }
        return null;
    }

    /**
     * Get public location URL
     */
    public function getPublicUrlAttribute()
    {
        return config('app.frontend_url', config('app.url')) . '/locations/' . $this->slug;
    }

    /**
     * Scope for searching locations
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }
        return $query;
    }

    /**
     * Scope for filtering by type
     */
    public function scopeFilterByType($query, $typeId)
    {
        if ($typeId) {
            return $query->where('location_type_id', $typeId);
        }
        return $query;
    }

    /**
     * Scope for filtering by parent
     */
    public function scopeFilterByParent($query, $parentId)
    {
        if ($parentId) {
            return $query->where('parent_id', $parentId);
        }
        return $query;
    }

    /**
     * Scope for company locations
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}