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

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 