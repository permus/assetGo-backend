<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_session_id',
        'path',
        'original_name',
        'file_type',
        'file_size',
    ];
}
