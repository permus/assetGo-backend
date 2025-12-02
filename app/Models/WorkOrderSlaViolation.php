<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderSlaViolation extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'sla_definition_id',
        'violation_type',
        'violated_at',
        'notified_at',
    ];

    protected $casts = [
        'violated_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /**
     * Get the work order that has this violation
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the SLA definition for this violation
     */
    public function slaDefinition(): BelongsTo
    {
        return $this->belongsTo(SlaDefinition::class);
    }
}
