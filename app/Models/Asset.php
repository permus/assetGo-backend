<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'name',
        'description',
        'category_id',
        'type',
        'serial_number',
        'model',
        'manufacturer',
        'capacity',
        'purchase_date',
        'purchase_price',
        'depreciation',
        'depreciation_life',
        'location_id',
        'department_id',
        'user_id',
        'company_id',
        'warranty',
        'insurance',
        'health_score',
        'status',
        'qr_code_path',
        'parent_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'depreciation' => 'decimal:2',
        'depreciation_life' => 'integer',
        'health_score' => 'decimal:2',
    ];

    protected $appends = ['qr_code_url', 'public_url', 'full_path', 'has_children', 'location_hierarchy'];

    public function getQrCodeUrlAttribute()
    {
        if ($this->qr_code_path) {
            return \Storage::disk('public')->url($this->qr_code_path);
        }
        return null;
    }

    public function getPublicUrlAttribute()
    {
        return env('WEBSITE_URL') . '/public/asset/' . $this->id;
    }

    public function getQuickChartQrUrlAttribute()
    {
        return 'https://quickchart.io/qr?text=' . urlencode($this->public_url) . '&margin=1&size=300';
    }

    protected static function booted()
    {
        static::deleting(function ($asset) {
            // Detach tags
            $asset->tags()->detach();
            // Delete images
            foreach ($asset->images as $image) {
                $image->delete();
            }
        });
        static::forceDeleted(function ($asset) {
            // Detach tags (in case not already detached)
            $asset->tags()->detach();
            // Delete images (in case not already deleted)
            foreach ($asset->images as $image) {
                $image->delete();
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class, 'type', 'name');
    }

    public function assetStatus()
    {
        return $this->belongsTo(AssetStatus::class, 'status', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function parent()
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

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

    public function getHasChildrenAttribute()
    {
        return $this->children()->exists();
    }

    public function getLocationHierarchyAttribute()
    {
        if ($this->location) {
            return $this->location->complete_hierarchy;
        }
        return null;
    }

    public function wouldCreateCircularReference($newParentId)
    {
        if (!$newParentId || $newParentId == $this->id) {
            return $newParentId == $this->id; // Self-parenting
        }

        // Check if new parent is a descendant of this asset
        $newParent = self::find($newParentId);
        if (!$newParent) {
            return false;
        }

        $ancestors = $newParent->ancestors();
        return $ancestors->contains('id', $this->id);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByParent($query, $parentId)
    {
        if ($parentId !== null) {
            return $query->where('parent_id', $parentId);
        }
        return $query;
    }

    public function scopeRootAssets($query)
    {
        return $query->whereNull('parent_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class)->with('ancestorsWithDetails');
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tags()
    {
        return $this->belongsToMany(AssetTag::class, 'asset_tag_pivot', 'asset_id', 'tag_id');
    }

    public function images()
    {
        return $this->hasMany(AssetImage::class);
    }

    public function transfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    public function activities()
    {
        return $this->hasMany(AssetActivity::class);
    }

    public function maintenanceSchedules()
    {
        return $this->hasMany(AssetMaintenanceSchedule::class);
    }
}
