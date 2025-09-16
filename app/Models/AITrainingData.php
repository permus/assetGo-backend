<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AITrainingData extends Model {
    protected $table = 'ai_training_data';
    protected $guarded = [];
    public $timestamps = false;
    
    protected $dates = ['created_at'];
}
