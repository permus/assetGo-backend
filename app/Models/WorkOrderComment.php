<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrderComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'user_id',
        'comment',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


