<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAnalyticsSchedule extends Model
{
    use HasFactory;

    protected $table = 'ai_analytics_schedule';

    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'enabled',
        'frequency',
        'hour_utc'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'hour_utc' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship with Company
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get frequency display name
     */
    public function getFrequencyDisplayNameAttribute(): string
    {
        return match($this->frequency) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            default => 'Weekly'
        };
    }

    /**
     * Get next run time
     */
    public function getNextRunTimeAttribute(): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $now = now();
        $hour = $this->hour_utc;

        switch ($this->frequency) {
            case 'daily':
                $nextRun = $now->copy()->setHour($hour)->setMinute(0)->setSecond(0);
                if ($nextRun->lte($now)) {
                    $nextRun->addDay();
                }
                break;
            case 'weekly':
                $nextRun = $now->copy()->next($now->dayOfWeek)->setHour($hour)->setMinute(0)->setSecond(0);
                break;
            case 'monthly':
                $nextRun = $now->copy()->addMonth()->setDay(1)->setHour($hour)->setMinute(0)->setSecond(0);
                break;
            default:
                return null;
        }

        return $nextRun->toISOString();
    }
}
