<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'status',
        'original_name',
        'stored_name',
        'file_type',
        'file_size',
        'uploaded_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'uploaded_at' => 'datetime',
    ];
}
