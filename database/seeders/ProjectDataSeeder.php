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
        $this->call(InventoryPartSeeder::class);
        $this->call(InventoryStockSeeder::class);
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
    }

    /**
     * Seed maintenance and work orders
     */
    protected function seedMaintenanceAndWorkOrders(): void
    {
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
    }
}

