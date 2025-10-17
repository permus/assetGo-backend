<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryPart;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Seeder;

class PurchaseOrderItemSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) return;

        $orders = PurchaseOrder::where('company_id', $company->id)->get();
        $parts = InventoryPart::where('company_id', $company->id)->get();

        if ($orders->count() == 0 || $parts->count() == 0) return;

        foreach ($orders as $order) {
            for ($i = 0; $i < rand(1, 3); $i++) {
                $part = $parts->random();
                $qty = rand(5, 50);

                PurchaseOrderItem::create([
                    'company_id' => $company->id,
                    'purchase_order_id' => $order->id,
                    'part_id' => $part->id,
                    'part_number' => $part->part_number,
                    'description' => $part->description ?? $part->name,
                    'ordered_qty' => $qty,
                    'unit_cost' => $part->unit_cost,
                    'line_total' => $qty * $part->unit_cost,
                ]);
            }
        }
        $this->command->info('Created purchase order items.');
    }
}
