<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'asset_tag_pivot', 'tag_id', 'asset_id');
    }
} 