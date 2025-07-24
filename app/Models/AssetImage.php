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