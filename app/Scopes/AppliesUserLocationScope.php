<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class AppliesUserLocationScope
{
    public static function apply(Builder $q, User $user, string $column = 'location_id'): void
    {
        $ids = $user->effectiveLocationIds(true);
        if (is_array($ids) && count($ids) > 0) {
            $q->whereIn($column, $ids);
        }
        // null -> full access, no filter
    }
}


