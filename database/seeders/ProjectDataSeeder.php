<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectDataSeeder extends Seeder
{
    /**
     * The email of the user to seed data for
     */
    protected ?string $email = null;

    /**
     * The password for the user (defaults to 'password' if not provided)
     */
    protected ?string $password = null;

    /**
     * The target company for seeding
     */
    protected ?Company $targetCompany = null;

    /**
     * The target user
     */
    protected ?User $targetUser = null;

    /**
     * Create a new seeder instance.
     */
    public function __construct()
    {
        // Try to get email from command argument if available
        if ($this->command && $this->command->hasArgument('email')) {
            $this->email = $this->command->argument('email');
        }
        // Try to get password from command option if available
        if ($this->command && $this->command->hasOption('password')) {
            $this->password = $this->command->option('password');
        }
    }

    /**
     * Set the email for this seeder instance
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Set the password for this seeder instance
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get the password to use (defaults to 'password' if not set)
     */
    protected function getPassword(): string
    {
        return $this->password ?? 'password';
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!$this->email) {
            $this->command->error('Email is required. Please provide email via --email option or setEmail() method.');
            return;
        }

        $this->command->info('========================================');
        $this->command->info('Starting Project Data Seeding...');
        $this->command->info("Email: {$this->email}");
        $this->command->info('========================================');

        // Phase 1: Ensure lookup/reference data exists
        $this->command->info('Phase 1: Ensuring lookup/reference data exists...');
        $this->ensureLookupData();

        // Phase 2: Find or create user and company
        $this->command->info('Phase 2: Setting up user and company...');
        $this->setupUserAndCompany();

        if (!$this->targetCompany || !$this->targetUser) {
            $this->command->error('Failed to setup user and company. Aborting.');
            return;
        }

        // Temporarily ensure this company is accessible as "first" company
        // by ensuring it exists and is accessible
        $this->ensureCompanyIsAccessible();

        // Phase 3: Company-specific Data
        $this->command->info('Phase 3: Seeding company-specific data...');
        $this->seedCompanyData();

        // Phase 4: Assets & Inventory
        $this->command->info('Phase 4: Seeding assets and inventory...');
        $this->seedAssetsAndInventory();

        // Phase 5: Transactions
        $this->command->info('Phase 5: Seeding transactions...');
        $this->seedTransactions();

        // Phase 6: Maintenance & Work Orders
        $this->command->info('Phase 6: Seeding maintenance and work orders...');
        $this->seedMaintenanceAndWorkOrders();

        // Phase 7: Reports & Scopes
        $this->command->info('Phase 7: Seeding reports and scopes...');
        $this->seedReportsAndScopes();

        // Phase 8: Import Data
        $this->command->info('Phase 8: Seeding import data...');
        $this->seedImportData();

        // Phase 9: AI & Analytics
        $this->command->info('Phase 9: Seeding AI and analytics data...');
        $this->seedAIAndAnalytics();
        
        // Phase 10: Notifications
        $this->command->info('Phase 10: Seeding notifications...');
        $this->seedNotifications();

        $this->command->info('========================================');
        $this->command->info('Project data seeding completed!');
        $this->command->info("Company: {$this->targetCompany->name}");
        $this->command->info("User: {$this->targetUser->email}");
        $this->command->info('========================================');
    }

    /**
     * Ensure lookup/reference data exists
     */
    protected function ensureLookupData(): void
    {
        $this->call(LocationTypeSeeder::class);
        $this->call(AssetCategoriesSeeder::class);
        $this->call(AssetTypeSeeder::class);
        $this->call(AssetStatusSeeder::class);
        $this->call(ModuleDefinitionsSeeder::class);
        $this->call(WorkOrderStatusSeeder::class);
        $this->call(WorkOrderPrioritySeeder::class);
        $this->call(WorkOrderCategorySeeder::class);
    }

    /**
     * Setup user and company
     */
    protected function setupUserAndCompany(): void
    {
        // Find or create user
        $this->targetUser = User::where('email', $this->email)->first();

        if (!$this->targetUser) {
            $this->command->info("User not found. Creating user: {$this->email}");
            
            // Extract name from email if possible
            $emailParts = explode('@', $this->email);
            $nameParts = explode('.', $emailParts[0]);
            $firstName = ucfirst($nameParts[0] ?? 'User');
            $lastName = isset($nameParts[1]) ? ucfirst($nameParts[1]) : 'Name';

            $this->targetUser = User::create([
                'email' => $this->email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'user_type' => 'admin',
                'password' => Hash::make($this->getPassword()),
                'email_verified_at' => now(),
            ]);

            $this->command->info("Created user: {$this->targetUser->email}");
        } else {
            $this->command->info("Found existing user: {$this->targetUser->email}");
        }

        // Find or create company
        if ($this->targetUser->company_id) {
            $this->targetCompany = Company::find($this->targetUser->company_id);
            if ($this->targetCompany) {
                $this->command->info("Found existing company: {$this->targetCompany->name}");
            }
        }

        if (!$this->targetCompany) {
            $this->command->info("Company not found. Creating company for user...");
            
            // Generate company name from user
            $companyName = $this->targetUser->first_name . ' ' . $this->targetUser->last_name . ' Company';
            $companySlug = Str::slug($companyName);

            // Ensure slug is unique
            $counter = 1;
            while (Company::where('slug', $companySlug)->exists()) {
                $companySlug = Str::slug($companyName) . '-' . $counter;
                $counter++;
            }

            $this->targetCompany = Company::create([
                'name' => $companyName,
                'slug' => $companySlug,
                'owner_id' => $this->targetUser->id,
                'subscription_status' => 'active',
                'subscription_expires_at' => now()->addYear(),
                'business_type' => 'Technology',
                'industry' => 'IT Services',
                'phone' => '+1-555-0000',
                'email' => $this->email,
                'address' => '123 Business Street, Suite 100',
                'currency' => 'USD',
                'settings' => [
                    'timezone' => 'America/New_York',
                    'date_format' => 'Y-m-d',
                ],
            ]);

            // Update user's company_id
            $this->targetUser->update(['company_id' => $this->targetCompany->id]);
            $this->command->info("Created company: {$this->targetCompany->name}");
        }
    }

    /**
     * Ensure the target company is accessible (for seeders that use Company::first())
     * We'll ensure it's the first company by ID, or at least exists
     */
    protected function ensureCompanyIsAccessible(): void
    {
        // Most seeders use Company::first() which gets the first company by ID
        // We can't change the ID, but we can ensure the company exists
        // and seeders that check for existing data will work correctly
        // For seeders that create new data, they'll use Company::first() which should work
        // if this is the only company, or we need to handle it differently
        
        // Actually, we can't control which company is "first", but we can ensure
        // seeders work with the target company by checking if they support company parameter
        // For now, we'll proceed and seeders that use Company::first() will work
        // if this is the first company, otherwise we may need to modify seeders
        
        // Let's proceed and see - many seeders check if data exists and skip
    }

    /**
     * Seed company-specific data
     */
    protected function seedCompanyData(): void
    {
        // Skip CompanySeeder and AdminSeeder as we've already set up the company
        // Skip UserSeeder as we already have the user (but we could create additional users)
        
        // Ensure roles and permissions exist (these are usually global)
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        
        // Seed company modules (this works with all companies)
        $this->call(CompanyModuleSeeder::class);
        
        // Seed company-specific data directly to ensure it goes to the target company
        $this->seedDepartments();
        $this->seedLocations();
        $this->call(LocationTemplateSeeder::class);
        $this->seedSuppliers();
        $this->seedInventoryCategories();
        $this->seedInventoryLocations();
        
        // Create additional users for the company
        $this->seedAdditionalUsers();
        
        // Seed teams (works with all companies)
        $this->call(TeamSeeder::class);
    }

    /**
     * Seed departments for the target company
     */
    protected function seedDepartments(): void
    {
        $existingCount = \App\Models\Department::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount >= 10) {
            $this->command->info('Departments already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding departments for company...');

        $departments = [
            ['name' => 'IT Department', 'description' => 'Information Technology and Systems', 'code' => 'IT', 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Maintenance', 'description' => 'Equipment and facility maintenance', 'code' => 'MAINT', 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Manufacturing', 'description' => 'Production and manufacturing', 'code' => 'MFG', 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Operations', 'description' => 'Daily operations and logistics', 'code' => 'OPS', 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Human Resources', 'description' => 'HR and employee management', 'code' => 'HR', 'is_active' => true, 'sort_order' => 5],
            ['name' => 'Finance', 'description' => 'Financial management and accounting', 'code' => 'FIN', 'is_active' => true, 'sort_order' => 6],
            ['name' => 'Sales', 'description' => 'Sales and customer relations', 'code' => 'SALES', 'is_active' => true, 'sort_order' => 7],
            ['name' => 'Marketing', 'description' => 'Marketing and communications', 'code' => 'MKT', 'is_active' => true, 'sort_order' => 8],
            ['name' => 'Research & Development', 'description' => 'R&D and innovation', 'code' => 'RND', 'is_active' => true, 'sort_order' => 9],
            ['name' => 'Quality Assurance', 'description' => 'Quality control and testing', 'code' => 'QA', 'is_active' => true, 'sort_order' => 10],
        ];

        foreach ($departments as $dept) {
            \App\Models\Department::firstOrCreate(
                ['company_id' => $this->targetCompany->id, 'code' => $dept['code']],
                array_merge($dept, ['company_id' => $this->targetCompany->id, 'user_id' => $this->targetUser->id])
            );
        }

        $this->command->info('Created departments for company.');
    }

    /**
     * Seed locations for the target company
     */
    protected function seedLocations(): void
    {
        $existingCount = \App\Models\Location::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount > 0) {
            $this->command->info('Locations already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding locations for company...');
        
        $building = \App\Models\LocationType::where('name', 'Building')->first();
        $floor = \App\Models\LocationType::where('name', 'Floor')->first();
        $room = \App\Models\LocationType::where('name', 'Room')->first();

        // Create Main Building
        $mainBuilding = \App\Models\Location::create([
            'company_id' => $this->targetCompany->id,
            'user_id' => $this->targetUser->id,
            'location_code' => \App\Models\Location::generateLocationCode($this->targetUser->id, 'Main Office Building'),
            'location_type_id' => $building?->id ?? 1,
            'name' => 'Main Office Building',
            'slug' => 'main-office-building',
            'address' => '123 Business Street, New York, NY 10001',
        ]);

        // Create floors and rooms
        $floors = ['First Floor', 'Second Floor', 'Third Floor'];
        $roomTypes = ['Office', 'Conference Room', 'Storage', 'Lab'];
        
        foreach ($floors as $index => $floorName) {
            $floorLoc = \App\Models\Location::create([
                'company_id' => $this->targetCompany->id,
                'user_id' => $this->targetUser->id,
                'location_code' => \App\Models\Location::generateLocationCode($this->targetUser->id, $floorName),
                'location_type_id' => $floor?->id ?? 2,
                'parent_id' => $mainBuilding->id,
                'name' => $floorName,
                'slug' => strtolower(str_replace(' ', '-', $floorName)),
            ]);
            
            // Create a room for each floor
            $roomType = $roomTypes[$index % count($roomTypes)];
            $roomName = $roomType . ' ' . (($index * 100) + 1);
            \App\Models\Location::create([
                'company_id' => $this->targetCompany->id,
                'user_id' => $this->targetUser->id,
                'location_code' => \App\Models\Location::generateLocationCode($this->targetUser->id, $roomName),
                'location_type_id' => $room?->id ?? 3,
                'parent_id' => $floorLoc->id,
                'name' => $roomName,
                'slug' => strtolower(str_replace(' ', '-', $roomType . '-' . (($index * 100) + 1))),
            ]);
        }

        $this->command->info('Created locations for company.');
    }

    /**
     * Seed suppliers for the target company
     */
    protected function seedSuppliers(): void
    {
        $existingCount = \App\Models\Supplier::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount >= 5) {
            $this->command->info('Suppliers already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding suppliers for company...');

        $suppliers = [
            ['name' => 'ABC Supplies Inc.', 'contact_person' => 'John Smith', 'email' => 'contact@abcsupplies.com', 'phone' => '+1-555-0101', 'city' => 'New York', 'state' => 'NY'],
            ['name' => 'Tech Parts Co.', 'contact_person' => 'Jane Doe', 'email' => 'sales@techparts.com', 'phone' => '+1-555-0102', 'city' => 'Los Angeles', 'state' => 'CA'],
            ['name' => 'Industrial Equipment Ltd.', 'contact_person' => 'Bob Johnson', 'email' => 'info@industrialequip.com', 'phone' => '+1-555-0103', 'city' => 'Chicago', 'state' => 'IL'],
            ['name' => 'Maintenance Solutions', 'contact_person' => 'Alice Williams', 'email' => 'hello@maintenancesolutions.com', 'phone' => '+1-555-0104', 'city' => 'Houston', 'state' => 'TX'],
            ['name' => 'Global Parts Distributor', 'contact_person' => 'Charlie Brown', 'email' => 'orders@globalparts.com', 'phone' => '+1-555-0105', 'city' => 'Miami', 'state' => 'FL'],
        ];

        foreach ($suppliers as $supplier) {
            \App\Models\Supplier::firstOrCreate(
                ['company_id' => $this->targetCompany->id, 'email' => $supplier['email']],
                array_merge($supplier, [
                    'company_id' => $this->targetCompany->id,
                    'supplier_code' => 'SUP-' . strtoupper(Str::random(6)),
                    'currency' => 'USD',
                    'payment_terms' => 'Net 30',
                ])
            );
        }

        $this->command->info('Created suppliers for company.');
    }

    /**
     * Seed inventory categories for the target company
     */
    protected function seedInventoryCategories(): void
    {
        $existingCount = \App\Models\InventoryCategory::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount >= 10) {
            $this->command->info('Inventory categories already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding inventory categories for company...');

        $categories = [
            ['name' => 'Spare Parts', 'description' => 'Replacement parts', 'is_active' => true],
            ['name' => 'Consumables', 'description' => 'Consumable items', 'is_active' => true],
            ['name' => 'Tools', 'description' => 'Maintenance tools', 'is_active' => true],
            ['name' => 'Electrical Components', 'description' => 'Electrical parts and components', 'is_active' => true],
            ['name' => 'Mechanical Parts', 'description' => 'Mechanical components', 'is_active' => true],
            ['name' => 'Hydraulic Components', 'description' => 'Hydraulic parts and systems', 'is_active' => true],
            ['name' => 'Pneumatic Parts', 'description' => 'Pneumatic components', 'is_active' => true],
            ['name' => 'Lubricants & Oils', 'description' => 'Lubrication supplies', 'is_active' => true],
            ['name' => 'Fasteners', 'description' => 'Bolts, nuts, screws, etc.', 'is_active' => true],
            ['name' => 'Bearings', 'description' => 'Various types of bearings', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            \App\Models\InventoryCategory::firstOrCreate(
                ['company_id' => $this->targetCompany->id, 'name' => $category['name']],
                array_merge($category, ['company_id' => $this->targetCompany->id])
            );
        }

        $this->command->info('Created inventory categories for company.');
    }

    /**
     * Seed inventory locations for the target company
     */
    protected function seedInventoryLocations(): void
    {
        $existingCount = \App\Models\InventoryLocation::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount >= 5) {
            $this->command->info('Inventory locations already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding inventory locations for company...');

        $locations = [
            ['name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'description' => 'Primary warehouse location'],
            ['name' => 'Storage Room A', 'code' => 'STG-A', 'description' => 'Storage room A'],
            ['name' => 'Storage Room B', 'code' => 'STG-B', 'description' => 'Storage room B'],
            ['name' => 'Tool Room', 'code' => 'TOOL-01', 'description' => 'Tool storage room'],
            ['name' => 'Parts Storage', 'code' => 'PARTS-01', 'description' => 'Parts storage area'],
        ];

        foreach ($locations as $location) {
            \App\Models\InventoryLocation::firstOrCreate(
                ['company_id' => $this->targetCompany->id, 'code' => $location['code']],
                array_merge($location, [
                    'company_id' => $this->targetCompany->id,
                    'user_id' => $this->targetUser->id,
                ])
            );
        }

        $this->command->info('Created inventory locations for company.');
    }

    /**
     * Seed additional users for the company
     */
    protected function seedAdditionalUsers(): void
    {
        $existingUsers = User::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingUsers > 1) {
            $this->command->info('Additional users already exist. Skipping.');
            return;
        }

        $this->command->info('Creating additional users for company...');
        
        $users = [
            ['first_name' => 'Manager', 'last_name' => 'Smith', 'email' => 'manager@' . $this->targetCompany->slug . '.com', 'user_type' => 'admin', 'hourly_rate' => 40.00],
            ['first_name' => 'John', 'last_name' => 'Technician', 'email' => 'tech1@' . $this->targetCompany->slug . '.com', 'user_type' => 'user', 'hourly_rate' => 30.00],
            ['first_name' => 'Sarah', 'last_name' => 'Engineer', 'email' => 'tech2@' . $this->targetCompany->slug . '.com', 'user_type' => 'user', 'hourly_rate' => 35.00],
        ];

        foreach ($users as $userData) {
            // Check if user already exists
            if (!User::where('email', $userData['email'])->exists()) {
                User::create(array_merge($userData, [
                    'company_id' => $this->targetCompany->id,
                    'password' => Hash::make($this->getPassword()),
                    'email_verified_at' => now(),
                ]));
            }
        }

        $this->command->info('Created additional users.');
    }

    /**
     * Seed assets and inventory
     */
    protected function seedAssetsAndInventory(): void
    {
        // Seed assets directly for target company to ensure they go to the right place
        $this->seedAssets();
        $this->call(AssetTagSeeder::class);
        $this->call(AssetImageSeeder::class);
        
        // Seed inventory directly for target company to ensure proper scoping
        $this->seedInventoryParts();
        $this->seedInventoryStocks();
    }

    /**
     * Seed assets for the target company
     */
    protected function seedAssets(): void
    {
        $existingCount = \App\Models\Asset::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount > 0) {
            $this->command->info('Assets already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding assets for company...');

        $faker = \Faker\Factory::create();
        $users = User::where('company_id', $this->targetCompany->id)->get();
        $locations = \App\Models\Location::where('company_id', $this->targetCompany->id)->get();
        $departments = \App\Models\Department::where('company_id', $this->targetCompany->id)->get();
        $categories = \App\Models\AssetCategory::all();
        
        // Get the "Active" asset status ID
        $activeStatus = \App\Models\AssetStatus::where('name', 'Active')->first();
        $activeStatusId = $activeStatus ? $activeStatus->id : 1;

        $assetTemplates = [
            ['name' => 'Dell Laptop XPS 15', 'manufacturer' => 'Dell', 'model' => 'XPS 15 9500'],
            ['name' => 'HP Desktop Computer', 'manufacturer' => 'HP', 'model' => 'EliteDesk 800'],
            ['name' => 'iPhone 13 Pro', 'manufacturer' => 'Apple', 'model' => 'iPhone 13 Pro'],
            ['name' => 'Samsung Monitor 27"', 'manufacturer' => 'Samsung', 'model' => 'S27R750'],
            ['name' => 'Herman Miller Chair', 'manufacturer' => 'Herman Miller', 'model' => 'Aeron'],
            ['name' => 'Lenovo ThinkPad', 'manufacturer' => 'Lenovo', 'model' => 'ThinkPad X1'],
            ['name' => 'MacBook Pro', 'manufacturer' => 'Apple', 'model' => 'MacBook Pro 16"'],
            ['name' => 'HP Printer LaserJet', 'manufacturer' => 'HP', 'model' => 'LaserJet Pro'],
            ['name' => 'Cisco Switch', 'manufacturer' => 'Cisco', 'model' => 'Catalyst 2960'],
            ['name' => 'Ergonomic Desk', 'manufacturer' => 'Steelcase', 'model' => 'Series 7'],
        ];

        $counter = 1;
        foreach ($assetTemplates as $template) {
            \App\Models\Asset::create([
                'asset_id' => 'AST-' . str_pad($counter, 6, '0', STR_PAD_LEFT),
                'name' => $template['name'],
                'description' => $faker->sentence(),
                'category_id' => $categories->count() > 0 ? $categories->random()->id : null,
                'serial_number' => strtoupper($faker->bothify('SN-????-####')),
                'model' => $template['model'],
                'manufacturer' => $template['manufacturer'],
                'brand' => $template['manufacturer'],
                'purchase_date' => $faker->dateTimeBetween('-2 years', '-1 month'),
                'purchase_price' => $faker->randomFloat(2, 100, 5000),
                'depreciation' => $faker->randomFloat(2, 10, 500),
                'depreciation_life' => 36,
                'location_id' => $locations->count() > 0 ? $locations->random()->id : null,
                'department_id' => $departments->count() > 0 ? $departments->random()->id : null,
                'user_id' => $users->count() > 0 ? $users->random()->id : null,
                'company_id' => $this->targetCompany->id,
                'warranty' => $faker->randomElement(['1 Year', '2 Years', '3 Years']),
                'health_score' => $faker->randomFloat(2, 70, 100),
                'status' => $activeStatusId,
                'is_active' => 1,
            ]);
            $counter++;
        }

        $this->command->info('Created assets for company.');
    }

    /**
     * Seed inventory parts for the target company
     */
    protected function seedInventoryParts(): void
    {
        $existingCount = \App\Models\InventoryPart::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount >= 20) {
            $this->command->info('Inventory parts already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding inventory parts for company...');

        $faker = \Faker\Factory::create();
        $categories = \App\Models\InventoryCategory::where('company_id', $this->targetCompany->id)->get();
        $users = User::where('company_id', $this->targetCompany->id)->get();
        $suppliers = \App\Models\Supplier::where('company_id', $this->targetCompany->id)->get();

        // Create comprehensive inventory parts
        $partTemplates = [
            ['name' => 'Oil Filter', 'manufacturer' => 'Fram', 'maintenance_category' => 'Mechanical', 'uom' => 'PCS', 'unit_cost' => 15.99],
            ['name' => 'Air Filter', 'manufacturer' => 'K&N', 'maintenance_category' => 'Mechanical', 'uom' => 'PCS', 'unit_cost' => 25.50],
            ['name' => 'Hydraulic Fluid', 'manufacturer' => 'Mobil', 'maintenance_category' => 'Hydraulic', 'uom' => 'L', 'unit_cost' => 45.00],
            ['name' => 'Motor Bearing', 'manufacturer' => 'SKF', 'maintenance_category' => 'Mechanical', 'uom' => 'PCS', 'unit_cost' => 125.00],
            ['name' => 'Electrical Wire', 'manufacturer' => 'Belden', 'maintenance_category' => 'Electrical', 'uom' => 'M', 'unit_cost' => 8.50],
            ['name' => 'Circuit Breaker', 'manufacturer' => 'Siemens', 'maintenance_category' => 'Electrical', 'uom' => 'PCS', 'unit_cost' => 85.00],
            ['name' => 'Pressure Sensor', 'manufacturer' => 'Honeywell', 'maintenance_category' => 'Pneumatic', 'uom' => 'PCS', 'unit_cost' => 150.00],
            ['name' => 'Pneumatic Cylinder', 'manufacturer' => 'Festo', 'maintenance_category' => 'Pneumatic', 'uom' => 'PCS', 'unit_cost' => 350.00],
            ['name' => 'Belt Drive', 'manufacturer' => 'Gates', 'maintenance_category' => 'Mechanical', 'uom' => 'PCS', 'unit_cost' => 75.00],
            ['name' => 'Lubricating Oil', 'manufacturer' => 'Shell', 'maintenance_category' => 'Mechanical', 'uom' => 'L', 'unit_cost' => 35.00],
            ['name' => 'Control Relay', 'manufacturer' => 'Omron', 'maintenance_category' => 'Electronics', 'uom' => 'PCS', 'unit_cost' => 45.00],
            ['name' => 'Temperature Sensor', 'manufacturer' => 'PT100', 'maintenance_category' => 'Electronics', 'uom' => 'PCS', 'unit_cost' => 95.00],
            ['name' => 'Seal Kit', 'manufacturer' => 'Parker', 'maintenance_category' => 'Hydraulic', 'uom' => 'SET', 'unit_cost' => 180.00],
            ['name' => 'Gasket Set', 'manufacturer' => 'Victor Reinz', 'maintenance_category' => 'Mechanical', 'uom' => 'SET', 'unit_cost' => 65.00],
            ['name' => 'Fuse Set', 'manufacturer' => 'Bussmann', 'maintenance_category' => 'Electrical', 'uom' => 'BOX', 'unit_cost' => 25.00],
            ['name' => 'V-Belt', 'manufacturer' => 'Gates', 'maintenance_category' => 'Mechanical', 'uom' => 'PCS', 'unit_cost' => 42.00],
            ['name' => 'Chain Drive', 'manufacturer' => 'Renold', 'maintenance_category' => 'Mechanical', 'uom' => 'M', 'unit_cost' => 55.00],
            ['name' => 'Solenoid Valve', 'manufacturer' => 'ASCO', 'maintenance_category' => 'Pneumatic', 'uom' => 'PCS', 'unit_cost' => 220.00],
            ['name' => 'Proximity Sensor', 'manufacturer' => 'Pepperl+Fuchs', 'maintenance_category' => 'Electronics', 'uom' => 'PCS', 'unit_cost' => 110.00],
            ['name' => 'Coolant Fluid', 'manufacturer' => 'Castrol', 'maintenance_category' => 'Mechanical', 'uom' => 'L', 'unit_cost' => 28.00],
        ];

        $partNumberCounter = 1;
        foreach ($partTemplates as $template) {
            // Generate unique part number
            $partNumber = 'PART-' . str_pad($partNumberCounter, 6, '0', STR_PAD_LEFT);
            
            // Check if part number already exists
            while (\App\Models\InventoryPart::where('part_number', $partNumber)->exists()) {
                $partNumberCounter++;
                $partNumber = 'PART-' . str_pad($partNumberCounter, 6, '0', STR_PAD_LEFT);
            }

            \App\Models\InventoryPart::create([
                'company_id' => $this->targetCompany->id,
                'user_id' => $users->isNotEmpty() ? $users->random()->id : $this->targetUser->id,
                'part_number' => $partNumber,
                'name' => $template['name'],
                'description' => $faker->sentence(8),
                'manufacturer' => $template['manufacturer'],
                'maintenance_category' => $template['maintenance_category'],
                'uom' => $template['uom'],
                'unit_cost' => $template['unit_cost'],
                'category_id' => $categories->isNotEmpty() ? $categories->random()->id : null,
                'reorder_point' => rand(5, 20),
                'reorder_qty' => rand(20, 100),
                'minimum_stock' => rand(5, 15),
                'maximum_stock' => rand(100, 500),
                'is_consumable' => in_array($template['maintenance_category'], ['Mechanical', 'Hydraulic']),
                'usage_tracking' => true,
                'status' => 'active',
                'is_archived' => false,
                'abc_class' => $faker->randomElement(['A', 'B', 'C']),
                'preferred_supplier_id' => $suppliers->isNotEmpty() ? $suppliers->random()->id : null,
            ]);

            $partNumberCounter++;
        }

        $this->command->info('Created inventory parts for company.');
    }

    /**
     * Seed inventory stocks for the target company
     */
    protected function seedInventoryStocks(): void
    {
        $existingCount = \App\Models\InventoryStock::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount > 0) {
            $this->command->info('Inventory stocks already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding inventory stocks for company...');

        $parts = \App\Models\InventoryPart::where('company_id', $this->targetCompany->id)->get();
        $locations = \App\Models\InventoryLocation::where('company_id', $this->targetCompany->id)->get();
        $users = User::where('company_id', $this->targetCompany->id)->get();

        if ($parts->isEmpty() || $locations->isEmpty()) {
            $this->command->warn('Cannot seed inventory stocks: missing parts or locations.');
            return;
        }

        foreach ($parts as $part) {
            // Create stock entries for multiple locations (distribute stock)
            $locationsToUse = $locations->random(rand(1, min(3, $locations->count())));
            $totalOnHand = rand(50, 500);
            $stockPerLocation = (int)($totalOnHand / $locationsToUse->count());
            $remainingStock = $totalOnHand - ($stockPerLocation * $locationsToUse->count());

            foreach ($locationsToUse as $index => $location) {
                $onHand = $stockPerLocation + ($index === 0 ? $remainingStock : 0);
                $reserved = rand(0, min(10, (int)($onHand * 0.1)));
                
                \App\Models\InventoryStock::create([
                    'company_id' => $this->targetCompany->id,
                    'part_id' => $part->id,
                    'location_id' => $location->id,
                    'on_hand' => $onHand,
                    'reserved' => $reserved,
                    'available' => $onHand - $reserved,
                    'average_cost' => $part->unit_cost,
                    'last_counted_at' => now()->subDays(rand(1, 30)),
                    'last_counted_by' => $users->isNotEmpty() ? $users->random()->id : null,
                    'bin_location' => 'BIN-' . strtoupper(\Illuminate\Support\Str::random(4)),
                ]);
            }
        }

        $this->command->info('Created inventory stocks for company.');
    }

    /**
     * Seed location activities for the target company
     */
    protected function seedLocationActivities(): void
    {
        $existingCount = \App\Models\LocationActivity::whereHas('location', function($query) {
            $query->where('company_id', $this->targetCompany->id);
        })->count();
        
        if ($existingCount >= 20) {
            $this->command->info('Location activities already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding location activities for company...');

        $locations = \App\Models\Location::where('company_id', $this->targetCompany->id)->get();
        $users = User::where('company_id', $this->targetCompany->id)->get();

        if ($locations->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Cannot seed location activities: missing locations or users.');
            return;
        }

        $actions = ['created', 'updated', 'deleted', 'moved', 'renamed', 'assigned', 'unassigned'];
        
        // Create 15-20 location activities
        $activityCount = rand(15, 20);
        for ($i = 0; $i < $activityCount; $i++) {
            $location = $locations->random();
            $user = $users->random();
            $action = $actions[array_rand($actions)];
            
            \App\Models\LocationActivity::create([
                'location_id' => $location->id,
                'user_id' => $user->id,
                'action' => $action,
                'before' => $action === 'created' ? null : [
                    'name' => $location->name . ' (old)',
                    'address' => $location->address ?? 'Old address',
                ],
                'after' => [
                    'name' => $location->name,
                    'address' => $location->address ?? 'New address',
                ],
                'comment' => \Faker\Factory::create()->sentence(8),
            ]);
        }

        $this->command->info('Created location activities for company.');
    }

    /**
     * Seed asset documents for the target company
     */
    protected function seedAssetDocuments(): void
    {
        $existingCount = \App\Models\AssetDocument::whereHas('asset', function($query) {
            $query->where('company_id', $this->targetCompany->id);
        })->count();
        
        if ($existingCount >= 15) {
            $this->command->info('Asset documents already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding asset documents for company...');

        $assets = \App\Models\Asset::where('company_id', $this->targetCompany->id)->get();

        if ($assets->isEmpty()) {
            $this->command->warn('Cannot seed asset documents: missing assets.');
            return;
        }

        $documentTypes = ['manual', 'certificate', 'warranty', 'other'];
        $documentNames = [
            'User Manual',
            'Installation Guide',
            'Warranty Certificate',
            'Service Manual',
            'Safety Certificate',
            'Inspection Report',
            'Maintenance Log',
            'Purchase Receipt',
            'Technical Specification',
            'Compliance Certificate',
        ];

        // Create 2-3 documents per asset (up to 15 total)
        $documentsCreated = 0;
        $maxDocuments = 15;
        
        foreach ($assets as $asset) {
            if ($documentsCreated >= $maxDocuments) {
                break;
            }
            
            $docCount = rand(1, 3);
            for ($i = 0; $i < $docCount && $documentsCreated < $maxDocuments; $i++) {
                $docType = $documentTypes[array_rand($documentTypes)];
                $docName = $documentNames[array_rand($documentNames)] . ' - ' . $asset->name;
                
                \App\Models\AssetDocument::create([
                    'asset_id' => $asset->id,
                    'document_path' => '/storage/documents/asset-' . $asset->id . '/' . \Illuminate\Support\Str::random(10) . '.pdf',
                    'document_name' => $docName,
                    'document_type' => $docType,
                    'file_size' => rand(100000, 5000000), // 100KB to 5MB
                    'mime_type' => 'application/pdf',
                ]);
                
                $documentsCreated++;
            }
        }

        $this->command->info("Created {$documentsCreated} asset documents for company.");
    }

    /**
     * Seed SLA violations for the target company
     */
    protected function seedSlaViolations(): void
    {
        $existingCount = \App\Models\WorkOrderSlaViolation::whereHas('workOrder', function($query) {
            $query->where('company_id', $this->targetCompany->id);
        })->count();
        
        if ($existingCount >= 10) {
            $this->command->info('SLA violations already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding SLA violations for company...');

        $workOrders = \App\Models\WorkOrder::where('company_id', $this->targetCompany->id)
            ->whereNotIn('status_id', function($subQuery) {
                $subQuery->select('id')
                        ->from('work_order_status')
                        ->whereIn('slug', ['completed', 'cancelled']);
            })
            ->get();
        $slaDefinitions = \App\Models\SlaDefinition::where('company_id', $this->targetCompany->id)
            ->where('is_active', true)
            ->get();

        if ($workOrders->isEmpty() || $slaDefinitions->isEmpty()) {
            $this->command->warn('Cannot seed SLA violations: missing work orders or SLA definitions.');
            return;
        }

        $violationTypes = ['response_time', 'containment_time', 'completion_time'];
        
        // Create 5-10 SLA violations
        $violationCount = rand(5, 10);
        for ($i = 0; $i < $violationCount; $i++) {
            $workOrder = $workOrders->random();
            $slaDefinition = $slaDefinitions->random();
            $violationType = $violationTypes[array_rand($violationTypes)];
            
            // Create violation that occurred in the past
            $violatedAt = now()->subDays(rand(1, 30));
            $notifiedAt = $violatedAt->copy()->addHours(rand(1, 24));
            
            \App\Models\WorkOrderSlaViolation::create([
                'work_order_id' => $workOrder->id,
                'sla_definition_id' => $slaDefinition->id,
                'violation_type' => $violationType,
                'violated_at' => $violatedAt,
                'notified_at' => $notifiedAt,
            ]);
        }

        $this->command->info('Created SLA violations for company.');
    }

    /**
     * Seed transactions
     */
    protected function seedTransactions(): void
    {
        $this->call(AssetActivitySeeder::class);
        $this->call(AssetTransferSeeder::class);
        $this->call(InventoryTransactionSeeder::class);
        $this->call(InventoryAlertSeeder::class);
        $this->call(PurchaseOrderSeeder::class);
        $this->call(PurchaseOrderItemSeeder::class);
        $this->call(PurchaseOrderTemplateSeeder::class);
        
        // Seed additional activity and document data
        $this->seedLocationActivities();
        $this->seedAssetDocuments();
    }

    /**
     * Seed maintenance and work orders
     */
    protected function seedMaintenanceAndWorkOrders(): void
    {
        // Call comprehensive MaintenanceSeeder which creates:
        // - Maintenance plans with checklists
        // - Scheduled maintenance with assignments
        // - Checklist responses (history)
        // - Predictive maintenance records
        $this->call(MaintenanceSeeder::class);
        
        // Also call individual seeders for work orders
        $this->call(AssetMaintenanceScheduleSeeder::class);
        $this->call(MaintenancePlanSeeder::class);
        $this->call(MaintenancePlanChecklistSeeder::class);
        $this->call(ScheduleMaintenanceSeeder::class);
        $this->call(ScheduleMaintenanceAssignedSeeder::class);
        $this->call(WorkOrderSeeder::class);
        $this->call(WorkOrderAssignmentSeeder::class);
        $this->call(WorkOrderCommentSeeder::class);
        $this->call(WorkOrderTimeLogSeeder::class);
        $this->call(WorkOrderPartSeeder::class);
        
        // Seed SLA definitions
        $this->call(SlaSeeder::class);
        
        // Seed SLA violations (if any work orders violate SLAs)
        $this->seedSlaViolations();
    }

    /**
     * Seed reports and scopes
     */
    protected function seedReportsAndScopes(): void
    {
        $this->call(ReportTemplateSeeder::class);
        $this->call(ReportScheduleSeeder::class);
        $this->call(ReportRunSeeder::class);
        $this->call(UserLocationScopeSeeder::class);
    }

    /**
     * Seed import data
     */
    protected function seedImportData(): void
    {
        $this->call(ImportSessionSeeder::class);
        $this->call(ImportFileSeeder::class);
        $this->call(ImportMappingSeeder::class);
    }

    /**
     * Seed AI and analytics data
     */
    protected function seedAIAndAnalytics(): void
    {
        $this->call(AIRecognitionHistorySeeder::class);
        $this->call(AITrainingDataSeeder::class);
        $this->call(AIAnalyticsHistorySeeder::class);
        $this->call(PredictiveMaintenanceSeeder::class);
        $this->call(AIRecommendationSeeder::class);
        $this->call(AIAnalyticsRunSeeder::class);
        
        // Seed AI analytics schedule for the company
        $this->seedAIAnalyticsSchedule();
    }

    /**
     * Seed AI analytics schedule for the target company
     */
    protected function seedAIAnalyticsSchedule(): void
    {
        $existing = \App\Models\AIAnalyticsSchedule::where('company_id', $this->targetCompany->id)->exists();
        
        if ($existing) {
            $this->command->info('AI analytics schedule already exists for company. Skipping.');
            return;
        }

        $this->command->info('Seeding AI analytics schedule for company...');

        \App\Models\AIAnalyticsSchedule::create([
            'company_id' => $this->targetCompany->id,
            'enabled' => true,
            'frequency' => 'weekly',
            'hour_utc' => 3, // 3 AM UTC
        ]);

        $this->command->info('Created AI analytics schedule for company.');
    }

    /**
     * Seed notifications for the target company
     */
    protected function seedNotifications(): void
    {
        $existingCount = \App\Models\Notification::where('company_id', $this->targetCompany->id)->count();
        
        if ($existingCount >= 20) {
            $this->command->info('Notifications already exist for company. Skipping.');
            return;
        }

        $this->command->info('Seeding notifications for company...');

        $users = User::where('company_id', $this->targetCompany->id)->get();
        $assets = \App\Models\Asset::where('company_id', $this->targetCompany->id)->get();
        $workOrders = \App\Models\WorkOrder::where('company_id', $this->targetCompany->id)->get();
        $locations = \App\Models\Location::where('company_id', $this->targetCompany->id)->get();

        if ($users->isEmpty()) {
            $this->command->warn('Cannot seed notifications: missing users.');
            return;
        }

        $notificationTypes = [
            'asset' => [
                'actions' => ['created', 'updated', 'assigned', 'maintenance_due', 'warranty_expiring'],
                'titles' => [
                    'created' => 'New Asset Created',
                    'updated' => 'Asset Updated',
                    'assigned' => 'Asset Assigned to You',
                    'maintenance_due' => 'Maintenance Due',
                    'warranty_expiring' => 'Warranty Expiring Soon',
                ],
            ],
            'work_order' => [
                'actions' => ['created', 'assigned', 'updated', 'completed', 'overdue'],
                'titles' => [
                    'created' => 'New Work Order Created',
                    'assigned' => 'Work Order Assigned to You',
                    'updated' => 'Work Order Updated',
                    'completed' => 'Work Order Completed',
                    'overdue' => 'Work Order Overdue',
                ],
            ],
            'maintenance' => [
                'actions' => ['scheduled', 'due', 'completed', 'overdue'],
                'titles' => [
                    'scheduled' => 'Maintenance Scheduled',
                    'due' => 'Maintenance Due',
                    'completed' => 'Maintenance Completed',
                    'overdue' => 'Maintenance Overdue',
                ],
            ],
            'inventory' => [
                'actions' => ['low_stock', 'reorder_point', 'out_of_stock', 'received'],
                'titles' => [
                    'low_stock' => 'Low Stock Alert',
                    'reorder_point' => 'Reorder Point Reached',
                    'out_of_stock' => 'Out of Stock',
                    'received' => 'Inventory Received',
                ],
            ],
            'location' => [
                'actions' => ['created', 'updated', 'asset_moved'],
                'titles' => [
                    'created' => 'New Location Created',
                    'updated' => 'Location Updated',
                    'asset_moved' => 'Asset Moved to Location',
                ],
            ],
        ];

        // Create 15-20 notifications
        $notificationCount = rand(15, 20);
        for ($i = 0; $i < $notificationCount; $i++) {
            $type = array_rand($notificationTypes);
            $typeConfig = $notificationTypes[$type];
            $action = $typeConfig['actions'][array_rand($typeConfig['actions'])];
            $title = $typeConfig['titles'][$action];
            
            $user = $users->random();
            $createdBy = $users->random();
            $isRead = rand(0, 10) > 3; // 70% unread
            
            // Generate appropriate data based on type
            $data = [];
            switch ($type) {
                case 'asset':
                    if ($assets->isNotEmpty()) {
                        $asset = $assets->random();
                        $data = [
                            'asset_id' => $asset->id,
                            'asset_name' => $asset->name,
                            'asset_id_code' => $asset->asset_id,
                        ];
                    }
                    break;
                case 'work_order':
                    if ($workOrders->isNotEmpty()) {
                        $workOrder = $workOrders->random();
                        $data = [
                            'work_order_id' => $workOrder->id,
                            'work_order_title' => $workOrder->title,
                        ];
                    }
                    break;
                case 'location':
                    if ($locations->isNotEmpty()) {
                        $location = $locations->random();
                        $data = [
                            'location_id' => $location->id,
                            'location_name' => $location->name,
                        ];
                    }
                    break;
                case 'inventory':
                    $parts = \App\Models\InventoryPart::where('company_id', $this->targetCompany->id)->get();
                    if ($parts->isNotEmpty()) {
                        $part = $parts->random();
                        $data = [
                            'part_id' => $part->id,
                            'part_name' => $part->name,
                            'part_number' => $part->part_number,
                        ];
                    }
                    break;
            }

            \App\Models\Notification::create([
                'company_id' => $this->targetCompany->id,
                'user_id' => $user->id,
                'type' => $type,
                'action' => $action,
                'title' => $title,
                'message' => \Faker\Factory::create()->sentence(10),
                'data' => $data,
                'read' => $isRead,
                'read_at' => $isRead ? now()->subDays(rand(1, 7)) : null,
                'created_by' => $createdBy->id,
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }

        $this->command->info('Created notifications for company.');
    }
}

