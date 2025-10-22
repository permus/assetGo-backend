<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 10000);
        $tax = $subtotal * 0.1;
        $shipping = fake()->randomFloat(2, 10, 100);
        $total = $subtotal + $tax + $shipping;
        $isApproved = fake()->boolean(70);

        return [
            'company_id' => Company::factory(),
            'po_number' => 'PO-' . fake()->unique()->numberBetween(10000, 99999),
            'supplier_id' => Supplier::factory(),
            'order_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'expected_date' => fake()->dateTimeBetween('now', '+3 months'),
            'status' => fake()->randomElement(['draft', 'pending', 'approved', 'ordered', 'received', 'cancelled']),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'created_by' => User::factory(),
            'approved_by' => $isApproved ? User::factory() : null,
            'approved_at' => $isApproved ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'reject_comment' => null,
            'vendor_name' => fake()->company(),
            'vendor_contact' => fake()->name(),
            'actual_delivery_date' => fake()->optional()->dateTimeBetween('-1 month', '+1 month'),
            'terms' => fake()->randomElement(['Net 30', 'Net 60', '2/10 Net 30', 'COD']),
            'approval_threshold' => 5000.00,
            'requires_approval' => $total > 5000,
            'approval_level' => fake()->numberBetween(1, 3),
            'approval_history' => null,
            'email_status' => fake()->randomElement(['not_sent', 'sent', 'delivered', 'failed']),
            'last_email_sent_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'template_id' => null,
        ];
    }
}

