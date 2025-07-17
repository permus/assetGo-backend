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
        'icon',
    ];

    /**
     * Get all locations of this type
     */
    public function locations()
    {
        return $this->hasMany(Location::class);
    }
}