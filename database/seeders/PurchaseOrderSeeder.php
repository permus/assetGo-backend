<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company || PurchaseOrder::count() > 0) return;

        $faker = Faker::create();
        $supplier = Supplier::where('company_id', $company->id)->first();
        $user = User::where('company_id', $company->id)->first();

        if (!$supplier || !$user) return;

        for ($i = 1; $i <= 1; $i++) {
            $subtotal = rand(500, 5000);
            $tax = $subtotal * 0.1;
            $shipping = rand(20, 100);
            
            PurchaseOrder::create([
                'company_id' => $company->id,
                'po_number' => 'PO-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier->id,
                'order_date' => now()->subDays(rand(1, 30)),
                'expected_date' => now()->addDays(rand(5, 30)),
                'status' => $faker->randomElement(['draft', 'pending', 'approved']),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $subtotal + $tax + $shipping,
                'created_by' => $user->id,
            ]);
        }
        $this->command->info('Created purchase orders.');
    }
}
