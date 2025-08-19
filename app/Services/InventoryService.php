<?php

namespace App\Services;

use App\Models\{InventoryPart, InventoryStock, InventoryTransaction, Location, PurchaseOrder, PurchaseOrderItem};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    private function assertPartBelongsToCompany(int $companyId, int $partId): void
    {
        $exists = InventoryPart::where('id', $partId)->where('company_id', $companyId)->exists();
        if (!$exists) {
            throw new InvalidArgumentException('Invalid part for company');
        }
    }

    private function assertLocationBelongsToCompany(int $companyId, int $locationId): void
    {
        $exists = Location::where('id', $locationId)->where('company_id', $companyId)->exists();
        if (!$exists) {
            throw new InvalidArgumentException('Invalid location for company');
        }
    }
    /**
     * Adjust stock levels and record a transaction
     */
    public function adjustStock(int $companyId, int $partId, int $locationId, int $quantity, string $type, array $options = []): InventoryTransaction
    {
        $this->assertPartBelongsToCompany($companyId, $partId);
        $this->assertLocationBelongsToCompany($companyId, $locationId);
        if (!in_array($type, ['receipt','issue','adjustment','transfer_out','transfer_in','return'])) {
            throw new InvalidArgumentException('Invalid transaction type');
        }

        return DB::transaction(function () use ($companyId, $partId, $locationId, $quantity, $type, $options) {
            $stock = InventoryStock::firstOrCreate(
                ['company_id' => $companyId, 'part_id' => $partId, 'location_id' => $locationId],
                ['on_hand' => 0, 'reserved' => 0, 'available' => 0, 'average_cost' => 0]
            );

            $unitCost = $options['unit_cost'] ?? null;

            if (in_array($type, ['issue','transfer_out']) && $quantity > $stock->available) {
                throw new InvalidArgumentException('Quantity exceeds available stock');
            }

            // Update quantities
            switch ($type) {
                case 'receipt':
                case 'transfer_in':
                case 'return':
                    $stock->on_hand += $quantity;
                    $stock->available += $quantity;
                    if ($unitCost === null || $unitCost < 0) {
                        $fallbackUnitCost = InventoryPart::where('id', $partId)->value('unit_cost');
                        if ($fallbackUnitCost !== null) {
                            $unitCost = (float) $fallbackUnitCost;
                        }
                    }
                    if ($unitCost !== null && $unitCost >= 0) {
                        // Simple moving average cost
                        $currentValue = $stock->average_cost * max(0, $stock->on_hand - $quantity);
                        $incomingValue = $unitCost * $quantity;
                        $newQty = max(1, $stock->on_hand); // prevent division by zero
                        $stock->average_cost = ($currentValue + $incomingValue) / $newQty;
                    }
                    break;
                case 'issue':
                case 'transfer_out':
                    $stock->on_hand -= $quantity;
                    $stock->available -= $quantity;
                    break;
                case 'adjustment':
                    $delta = $options['delta'] ?? $quantity;
                    // Capture previous quantity before adjustment for average cost calc
                    $previousQty = $stock->on_hand;
                    $stock->on_hand += $delta;
                    $stock->available += $delta;
                    // If adjustment increases stock and unit cost is provided, update moving average
                    if ($delta > 0) {
                        if ($unitCost === null || $unitCost < 0) {
                            $fallbackUnitCost = InventoryPart::where('id', $partId)->value('unit_cost');
                            if ($fallbackUnitCost !== null) {
                                $unitCost = (float) $fallbackUnitCost;
                            }
                        }
                        if ($unitCost !== null && $unitCost >= 0) {
                        $currentValue = $stock->average_cost * max(0, $previousQty);
                        $incomingValue = $unitCost * $delta;
                        $newQty = max(1, $stock->on_hand);
                        $stock->average_cost = ($currentValue + $incomingValue) / $newQty;
                        }
                    }
                    break;
            }

            if ($stock->on_hand < 0 || $stock->available < 0) {
                throw new InvalidArgumentException('Stock cannot be negative');
            }

            $stock->save();

            return InventoryTransaction::create([
                'company_id' => $companyId,
                'part_id' => $partId,
                'location_id' => $locationId,
                'from_location_id' => $options['from_location_id'] ?? null,
                'to_location_id' => $options['to_location_id'] ?? null,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? $unitCost * $quantity : null,
                'reason' => $options['reason'] ?? null,
                'notes' => $options['notes'] ?? null,
                'reference' => $options['reference'] ?? null,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'related_id' => $options['related_id'] ?? null,
                'user_id' => $options['user_id'] ?? null,
            ]);
        });
    }

    /**
     * Transfer between locations
     */
    public function transfer(int $companyId, int $partId, int $fromLocationId, int $toLocationId, int $quantity, array $options = []): array
    {
        $this->assertPartBelongsToCompany($companyId, $partId);
        $this->assertLocationBelongsToCompany($companyId, $fromLocationId);
        $this->assertLocationBelongsToCompany($companyId, $toLocationId);
        $out = $this->adjustStock($companyId, $partId, $fromLocationId, $quantity, 'transfer_out', [
            'reason' => $options['reason'] ?? 'transfer',
            'notes' => $options['notes'] ?? null,
            'reference' => $options['reference'] ?? null,
            'user_id' => $options['user_id'] ?? null,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'reference_type' => 'transfer',
        ]);

        // If no unit_cost provided, default to source stock's average_cost
        $unitCost = $options['unit_cost'] ?? null;
        if ($unitCost === null) {
            $sourceStock = InventoryStock::where([
                'company_id' => $companyId,
                'part_id' => $partId,
                'location_id' => $fromLocationId,
            ])->first();
            if ($sourceStock) {
                $unitCost = $sourceStock->average_cost;
            }
        }

        $in = $this->adjustStock($companyId, $partId, $toLocationId, $quantity, 'transfer_in', [
            'reason' => $options['reason'] ?? 'transfer',
            'notes' => $options['notes'] ?? null,
            'reference' => $options['reference'] ?? null,
            'user_id' => $options['user_id'] ?? null,
            'unit_cost' => $unitCost,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'reference_type' => 'transfer',
        ]);

        return [$out, $in];
    }

    /**
     * Reserve stock (increase reserved, decrease available)
     */
    public function reserveStock(int $companyId, int $partId, int $locationId, int $quantity, array $options = []): InventoryStock
    {
        $this->assertPartBelongsToCompany($companyId, $partId);
        $this->assertLocationBelongsToCompany($companyId, $locationId);
        return DB::transaction(function () use ($companyId, $partId, $locationId, $quantity) {
            $stock = InventoryStock::firstOrCreate(
                ['company_id' => $companyId, 'part_id' => $partId, 'location_id' => $locationId],
                ['on_hand' => 0, 'reserved' => 0, 'available' => 0, 'average_cost' => 0]
            );
            if ($quantity > $stock->available) {
                throw new InvalidArgumentException('Quantity exceeds available stock');
            }
            $stock->reserved += $quantity;
            $stock->available -= $quantity;
            $stock->save();
            return $stock;
        });
    }

    /**
     * Release reserved stock (decrease reserved, increase available)
     */
    public function releaseReservedStock(int $companyId, int $partId, int $locationId, int $quantity, array $options = []): InventoryStock
    {
        $this->assertPartBelongsToCompany($companyId, $partId);
        $this->assertLocationBelongsToCompany($companyId, $locationId);
        return DB::transaction(function () use ($companyId, $partId, $locationId, $quantity) {
            $stock = InventoryStock::firstOrCreate(
                ['company_id' => $companyId, 'part_id' => $partId, 'location_id' => $locationId],
                ['on_hand' => 0, 'reserved' => 0, 'available' => 0, 'average_cost' => 0]
            );
            if ($quantity > $stock->reserved) {
                throw new InvalidArgumentException('Quantity exceeds reserved stock');
            }
            $stock->reserved -= $quantity;
            $stock->available += $quantity;
            $stock->save();
            return $stock;
        });
    }

    /**
     * Perform physical count and auto-adjust
     */
    public function performStockCount(int $companyId, int $partId, int $locationId, int $countedQuantity, array $options = [])
    {
        $this->assertPartBelongsToCompany($companyId, $partId);
        $this->assertLocationBelongsToCompany($companyId, $locationId);
        return DB::transaction(function () use ($companyId, $partId, $locationId, $countedQuantity, $options) {
            $stock = InventoryStock::firstOrCreate(
                ['company_id' => $companyId, 'part_id' => $partId, 'location_id' => $locationId],
                ['on_hand' => 0, 'reserved' => 0, 'available' => 0, 'average_cost' => 0]
            );
            $delta = $countedQuantity - $stock->on_hand;
            if ($delta !== 0) {
                // Use adjustStock with delta
                $this->adjustStock($companyId, $partId, $locationId, abs($delta), 'adjustment', [
                    'delta' => $delta,
                    'reason' => 'Physical Count',
                    'notes' => $options['notes'] ?? null,
                    'user_id' => $options['user_id'] ?? null,
                    'reference_type' => 'stock_count',
                ]);
            }
            $stock->last_counted_at = now();
            $stock->last_counted_by = $options['user_id'] ?? null;
            $stock->save();
            return ['stock' => $stock->fresh(), 'adjustment' => $delta];
        });
    }
}


