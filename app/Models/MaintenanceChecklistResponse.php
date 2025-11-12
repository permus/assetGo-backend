<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceChecklistResponse extends Model
{
    use HasFactory;

    protected $table = 'maintenance_checklist_responses';

    protected $fillable = [
        'schedule_maintenance_assigned_id',
        'checklist_item_id',
        'user_id',
        'response_type',
        'response_value',
        'photo_url',
        'completed_at',
    ];

    protected $casts = [
        'response_value' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the assignment this response belongs to
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleMaintenanceAssigned::class, 'schedule_maintenance_assigned_id');
    }

    /**
     * Get the checklist item this response is for
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlanChecklist::class, 'checklist_item_id');
    }

    /**
     * Get the user who completed this response
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if response is completed
     */
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    /**
     * Mark response as completed
     */
    public function markCompleted(): void
    {
        if (!$this->completed_at) {
            $this->completed_at = now();
            $this->save();
        }
    }

    /**
     * Get response based on type
     */
    public function getFormattedResponse()
    {
        switch ($this->response_type) {
            case 'checkbox':
                return $this->response_value['checked'] ?? false;
            case 'pass_fail':
                return $this->response_value['result'] ?? null;
            case 'text_input':
                return $this->response_value['text'] ?? '';
            case 'measurements':
                return $this->response_value['measurements'] ?? [];
            case 'photo_capture':
                return $this->photo_url;
            default:
                return $this->response_value;
        }
    }
}

