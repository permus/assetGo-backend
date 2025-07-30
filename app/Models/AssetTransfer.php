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
        'old_location_id',
        'new_location_id',
        'old_department_id',
        'new_department_id',
        'from_user_id',
        'to_user_id',
        'reason',
        'transfer_date',
        'notes',
        'condition_report',
        'status',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function oldLocation()
    {
        return $this->belongsTo(Location::class, 'old_location_id');
    }

    public function newLocation()
    {
        return $this->belongsTo(Location::class, 'new_location_id');
    }

    public function oldDepartment()
    {
        return $this->belongsTo(Department::class, 'old_department_id');
    }

    public function newDepartment()
    {
        return $this->belongsTo(Department::class, 'new_department_id');
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 