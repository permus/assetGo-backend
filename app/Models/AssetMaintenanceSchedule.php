<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetMaintenanceSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'schedule_type',
        'next_due',
        'last_done',
        'frequency',
        'notes',
        'status',
    ];

    protected $casts = [
        'next_due' => 'date',
        'last_done' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 