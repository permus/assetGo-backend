<?php

namespace App\Services;

use App\Models\{InventoryPart, InventoryStock, InventoryTransaction, InventoryLocation, PurchaseOrder, PurchaseOrderItem};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Adjust stock levels and record a transaction
     */
    public function adjustStock(int $companyId, int $partId, int $locationId, int $quantity, string $type, array $options = []): InventoryTransaction
    {
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
                    $stock->on_hand += $delta;
                    $stock->available += $delta;
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
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? $unitCost * $quantity : null,
                'reason' => $options['reason'] ?? null,
                'notes' => $options['notes'] ?? null,
                'reference' => $options['reference'] ?? null,
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
        $out = $this->adjustStock($companyId, $partId, $fromLocationId, $quantity, 'transfer_out', [
            'reason' => $options['reason'] ?? 'transfer',
            'notes' => $options['notes'] ?? null,
            'reference' => $options['reference'] ?? null,
            'user_id' => $options['user_id'] ?? null,
        ]);

        $in = $this->adjustStock($companyId, $partId, $toLocationId, $quantity, 'transfer_in', [
            'reason' => $options['reason'] ?? 'transfer',
            'notes' => $options['notes'] ?? null,
            'reference' => $options['reference'] ?? null,
            'user_id' => $options['user_id'] ?? null,
            'unit_cost' => $options['unit_cost'] ?? null,
        ]);

        return [$out, $in];
    }
}


