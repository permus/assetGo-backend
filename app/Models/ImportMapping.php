<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_session_id',
        'mappings',
        'user_overrides',
    ];

    protected $casts = [
        'mappings' => 'array',
        'user_overrides' => 'array',
    ];
}
