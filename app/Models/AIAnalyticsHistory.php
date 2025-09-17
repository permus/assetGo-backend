<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIAnalyticsHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'asset_count',
        'image_count',
        'analytics_result',
        'health_score'
    ];

    protected $casts = [
        'analytics_result' => 'array'
    ];
}
