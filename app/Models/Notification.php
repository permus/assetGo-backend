<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'type',
        'action',
        'title',
        'message',
        'data',
        'read_at',
        'read',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who receives this notification
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the company this notification belongs to
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the user who created/triggered this notification
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include unread notifications
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('read', false)->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('read', true)->whereNotNull('read_at');
    }

    /**
     * Scope a query to only include notifications for a specific user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include recent notifications
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope a query to filter by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by company
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Check if notification is read
     */
    public function getIsReadAttribute(): bool
    {
        return $this->read && $this->read_at !== null;
    }

    /**
     * Get human-readable time ago
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}
