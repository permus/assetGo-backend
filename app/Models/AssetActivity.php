<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
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

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 