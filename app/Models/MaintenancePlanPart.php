<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_plan_id',
        'part_id',
        'default_qty',
        'is_required',
    ];

    protected $casts = [
        'default_qty' => 'decimal:3',
        'is_required' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(InventoryPart::class, 'part_id');
    }
}

