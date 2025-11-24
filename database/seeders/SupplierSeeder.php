<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Company;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company or create one
        $company = Company::first();
        if (!$company) {
            // Create a default user first (required for owner_id)
            $user = \App\Models\User::create([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@defaultcompany.com',
                'password' => bcrypt('password'),
                'user_type' => 'company',
            ]);
            
            $company = Company::create([
                'name' => 'Default Company',
                'slug' => 'default-company',
                'owner_id' => $user->id,
                'email' => 'admin@defaultcompany.com',
                'phone' => '+1-555-0000',
                'address' => '123 Main St, City, Country'
            ]);
            
            // Update user's company_id
            $user->update(['company_id' => $company->id]);
        }

        // Create sample suppliers
        $suppliers = [
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-ELEC001',
                'name' => 'Electronics Plus Ltd.',
                'contact_person' => 'Sarah Johnson',
                'tax_registration_number' => 'TRN-12345678',
                'email' => 'orders@electronicsplus.com',
                'phone' => '+1-555-0101',
                'alternate_phone' => '+1-555-0102',
                'website' => 'https://electronicsplus.com',
                'street_address' => '456 Tech Blvd',
                'city' => 'Silicon Valley',
                'state' => 'CA',
                'postal_code' => '94025',
                'payment_terms' => 'Net 30 days',
                'terms' => 'Standard electronics supplier terms and conditions',
                'currency' => 'USD',
                'credit_limit' => 50000.00,
                'delivery_lead_time' => 7,
                'notes' => 'Reliable supplier for electronic components and accessories'
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-OFFICE002',
                'name' => 'Office Supplies Pro',
                'contact_person' => 'Michael Chen',
                'tax_registration_number' => 'TRN-87654321',
                'email' => 'sales@officesuppliespro.com',
                'phone' => '+1-555-0201',
                'alternate_phone' => '+1-555-0202',
                'website' => 'https://officesuppliespro.com',
                'street_address' => '789 Business Ave',
                'city' => 'Downtown',
                'state' => 'NY',
                'postal_code' => '10001',
                'payment_terms' => '2/10 Net 30',
                'terms' => 'Office supplies and furniture supplier',
                'currency' => 'USD',
                'credit_limit' => 25000.00,
                'delivery_lead_time' => 3,
                'notes' => 'Fast delivery for office supplies, good for urgent orders'
            ],
            [
                'company_id' => $company->id,
                'supplier_code' => 'SUP-IT003',
                'name' => 'IT Solutions Hub',
                'contact_person' => 'David Rodriguez',
                'tax_registration_number' => 'TRN-11223344',
                'email' => 'procurement@itsolutionshub.com',
                'phone' => '+1-555-0301',
                'alternate_phone' => '+1-555-0302',
                'website' => 'https://itsolutionshub.com',
                'street_address' => '321 Innovation Dr',
                'city' => 'Tech Park',
                'state' => 'TX',
                'postal_code' => '75001',
                'payment_terms' => 'Net 45 days',
                'terms' => 'IT hardware and software solutions provider',
                'currency' => 'USD',
                'credit_limit' => 100000.00,
                'delivery_lead_time' => 14,
                'notes' => 'Premium IT supplier with extended payment terms'
            ]
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        // Create additional random suppliers using factory
        Supplier::factory(8)->create([
            'company_id' => $company->id
        ]);

        $this->command->info('Created ' . Supplier::where('company_id', $company->id)->count() . ' suppliers successfully!');
    }
}
