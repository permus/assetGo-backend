<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'image_path',
        'caption',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            // Check if it's already a full URL (e.g., placeholder service)
            if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
                return $this->image_path;
            }
            
            // Check if file exists in storage
            if (\Storage::disk('public')->exists($this->image_path)) {
                return \Storage::disk('public')->url($this->image_path);
            }
            
            // Return placeholder if file doesn't exist
            return 'https://via.placeholder.com/600x400/4F46E5/FFFFFF?text=Asset+Image';
        }
        return 'https://via.placeholder.com/600x400/9CA3AF/FFFFFF?text=No+Image';
    }

    protected static function booted()
    {
        static::deleting(function ($image) {
            if ($image->image_path && \Storage::disk('public')->exists($image->image_path)) {
                \Storage::disk('public')->delete($image->image_path);
            }
        });
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 