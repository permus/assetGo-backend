<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrderPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'part_id',
        'location_id',
        'qty',
        'unit_cost',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function part()
    {
        return $this->belongsTo(\App\Models\InventoryPart::class, 'part_id');
    }
}


