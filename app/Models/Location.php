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
        'user_id',
        'location_type_id',
        'parent_id',
        'name',
        'slug',
        'address',
        'description',
        'qr_code_path',
        'hierarchy_level',
    ];

    protected $casts = [
    ];

    protected $appends = [
        'full_path',
        'public_url',
        'has_children',
        'qr_code_url',
        'quick_chart_qr_url',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($location) {
            // Auto-generate slug if not provided
            if (empty($location->slug)) {
                $location->slug = $location->generateUniqueSlug($location->name);
            }

            // Auto-calculate hierarchy level
            $location->hierarchy_level = $location->calculateHierarchyLevel();
        });

        static::updating(function ($location) {
            // Recalculate hierarchy level if parent changed
            if ($location->isDirty('parent_id')) {
                $location->hierarchy_level = $location->calculateHierarchyLevel();
            }

            // Update slug if name changed
            if ($location->isDirty('name') && empty($location->slug)) {
                $location->slug = $location->generateUniqueSlug($location->name);
            }
        });

        static::created(function ($location) {
            // Create asset summary record
            $location->createAssetSummary();
        });

        static::deleting(function ($location) {
            // Delete QR code file
            if ($location->qr_code_path) {
                \Storage::disk('public')->delete($location->qr_code_path);
            }
        });
    }

    /**
     * Get the company that owns this location
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created this location
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the location type
     */
    public function type()
    {
        return $this->belongsTo(LocationType::class, 'location_type_id');
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
     * Get all ancestors
     */
    public function ancestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get asset summary
     */
    public function assetSummary()
    {
        return $this->hasOne(LocationAssetSummary::class);
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (self::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Calculate hierarchy level based on parent chain
     */
    public function calculateHierarchyLevel()
    {
        if (!$this->parent_id) {
            return 0;
        }

        $parent = self::find($this->parent_id);
        return $parent ? $parent->hierarchy_level + 1 : 0;
    }

    /**
     * Get full path attribute
     */
    public function getFullPathAttribute()
    {
        $path = collect([$this->name]);
        $current = $this->parent;

        while ($current) {
            $path->prepend($current->name);
            $current = $current->parent;
        }

        return $path->implode(' → ');
    }

    /**
     * Get public URL attribute
     */
    public function getPublicUrlAttribute()
    {
        return env('WEBSITE_URL') . '/public/location/' . $this->id;
    }

    /**
     * Check if location has children
     */
    public function getHasChildrenAttribute()
    {
        return $this->children()->exists();
    }

    /**
     * Get full QR code URL attribute
     */
    public function getQrCodeUrlAttribute()
    {
        if ($this->qr_code_path) {
            return \Storage::disk('public')->url($this->qr_code_path);
        }
        return null;
    }

    /**
     * Get QuickChart QR code URL attribute
     */
    public function getQuickChartQrUrlAttribute()
    {
        return 'https://quickchart.io/qr?text=' . urlencode($this->public_url) . '&margin=1&size=300';
    }

    /**
     * Get asset summary data
     */
    public function getAssetSummaryData()
    {
        $summary = $this->assetSummary;
        return $summary ? [
            'asset_count' => $summary->asset_count,
            'health_score' => $summary->health_score,
        ] : [
            'asset_count' => 0,
            'health_score' => 100.00,
        ];
    }

    /**
     * Create asset summary record
     */
    public function createAssetSummary()
    {
        if (!$this->assetSummary()->exists()) {
            LocationAssetSummary::create([
                'location_id' => $this->id,
                'company_id' => $this->company_id,
                'user_id' => $this->user_id,
                'asset_count' => 0,
                'health_score' => 100.00,
            ]);
        }
    }

    /**
     * Check if moving to a parent would create circular reference
     */
    public function wouldCreateCircularReference($newParentId)
    {
        if (!$newParentId || $newParentId == $this->id) {
            return $newParentId == $this->id; // Self-parenting
        }

        // Check if new parent is a descendant of this location
        $newParent = self::find($newParentId);
        if (!$newParent) {
            return false;
        }

        $ancestors = $newParent->ancestors();
        return $ancestors->contains('id', $this->id);
    }

    /**
     * Check if location can be moved to new parent based on type compatibility
     */
    public function canMoveToParent($newParentId)
    {
        if (!$newParentId) {
            // Moving to root level - check if type allows it
            return $this->type->hierarchy_level === 0;
        }

        $newParent = self::find($newParentId);
        if (!$newParent) {
            return false;
        }

        // Check type compatibility
        return $this->type->canBeChildOf($newParent->type);
    }

    /**
     * Get maximum allowed depth from this location
     */
    public function getMaxDepthFromHere()
    {
        return 3 - $this->hierarchy_level; // Max 4 levels (0-3)
    }

    /**
     * Scope for company locations
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for search
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
    public function scopeByType($query, $typeId)
    {
        if ($typeId) {
            return $query->where('location_type_id', $typeId);
        }
        return $query;
    }

    /**
     * Scope for filtering by parent
     */
    public function scopeByParent($query, $parentId)
    {
        if ($parentId !== null) {
            return $query->where('parent_id', $parentId);
        }
        return $query;
    }

    /**
     * Scope for filtering by hierarchy level
     */
    public function scopeByHierarchyLevel($query, $hierarchyLevel)
    {
        if ($hierarchyLevel !== null) {
            return $query->where('hierarchy_level', $hierarchyLevel);
        }
        return $query;
    }
}
