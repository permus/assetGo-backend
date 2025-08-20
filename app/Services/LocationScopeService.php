<?php

namespace App\Services;

use App\Models\Location;

class LocationScopeService
{
    public function expandWithDescendants(array $rootIds, int $companyId): array
    {
        $seen = [];
        $queue = $rootIds;

        while (!empty($queue)) {
            $batch = array_values(array_unique(array_diff($queue, $seen)));
            if (empty($batch)) {
                break;
            }

            $seen = array_merge($seen, $batch);

            $children = Location::query()
                ->where('company_id', $companyId)
                ->whereIn('parent_id', $batch)
                ->pluck('id')
                ->all();

            $queue = $children;
        }

        return array_values(array_unique($seen));
    }
}


