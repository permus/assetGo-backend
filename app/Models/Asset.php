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
        'purchase_date',
        'purchase_price',
        'depreciation',
        'location_id',
        'department_id',
        'user_id',
        'company_id',
        'warranty',
        'insurance',
        'health_score',
        'status',
        'qr_code_path',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'depreciation' => 'decimal:2',
        'health_score' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
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