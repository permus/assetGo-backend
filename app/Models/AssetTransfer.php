<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'from_location_id',
        'to_location_id',
        'from_user_id',
        'to_user_id',
        'transfer_date',
        'notes',
        'condition_report',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
} 