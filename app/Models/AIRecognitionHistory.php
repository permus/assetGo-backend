<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIRecognitionHistory extends Model {
    protected $table = 'ai_recognition_history';
    protected $guarded = [];
    protected $casts = [
        'image_paths' => 'array',
        'recognition_result' => 'array',
    ];
}
