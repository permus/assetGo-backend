<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationAssetSummary extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'location_asset_summary';

    protected $fillable = [
        'location_id',
        'company_id',
        'user_id',
        'asset_count',
        'health_score',
    ];

    protected $casts = [
        'health_score' => 'decimal:2',
    ];

    /**
     * Get the location this summary belongs to
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Update asset count
     */
    public function updateAssetCount($count)
    {
        $this->update(['asset_count' => $count]);
    }

    /**
     * Update health score
     */
    public function updateHealthScore($score)
    {
        $this->update(['health_score' => $score]);
    }

    /**
     * Check if location has significant assets (for move warnings)
     */
    public function hasSignificantAssets()
    {
        return $this->asset_count > 10;
    }
}