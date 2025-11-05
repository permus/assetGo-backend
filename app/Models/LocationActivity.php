<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'location_id',
        'user_id',
        'action',
        'before',
        'after',
        'comment',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    /**
     * Get the location that owns this activity
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

