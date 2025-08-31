<?php

namespace App\Services\Maintenance;

use App\Models\MaintenancePlan;
use Carbon\Carbon;

class DueDateService
{
    public function calculateDueDate(MaintenancePlan $plan, ?Carbon $start): ?Carbon
    {
        if ($plan->frequency_type !== 'time') {
            return null;
        }

        $value = (int)($plan->frequency_value ?? 0);
        $unit = $plan->frequency_unit;
        if ($value <= 0 || !$unit) {
            return null;
        }

        $base = $start ? $start->copy() : Carbon::now();

        return match ($unit) {
            'days' => $base->copy()->addDays($value),
            'weeks' => $base->copy()->addWeeks($value),
            'months' => $base->copy()->addMonths($value),
            'years' => $base->copy()->addYears($value),
            default => null,
        };
    }
}


