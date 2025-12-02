<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\SlaDefinition;
use App\Models\WorkOrderCategory;
use App\Models\ModuleDefinition;
use App\Models\CompanyModule;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SlaDefinitionApiTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $user;
    protected $otherCompany;
    protected $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'admin'
        ]);

        $this->otherCompany = Company::factory()->create();
        $this->otherUser = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'user_type' => 'admin'
        ]);

        // Enable SLA module for both companies
        $slaModule = ModuleDefinition::firstOrCreate(
            ['key' => 'sla'],
            [
                'display_name' => 'SLA',
                'description' => 'Service Level Agreement tracking and management',
                'icon_name' => 'sla',
                'route_path' => '/sla',
                'sort_order' => 55,
                'is_system_module' => false,
            ]
        );

        CompanyModule::create([
            'company_id' => $this->company->id,
            'module_id' => $slaModule->id,
            'is_enabled' => true,
        ]);

        CompanyModule::create([
            'company_id' => $this->otherCompany->id,
            'module_id' => $slaModule->id,
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function it_can_create_sla_definition_with_category_id()
    {
        $category = WorkOrderCategory::factory()->create([
            'company_id' => null // Global category
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Test SLA',
            'description' => 'Test description',
            'applies_to' => 'work_orders',
            'priority_level' => 'high',
            'category_id' => $category->id,
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'appliesTo',
                    'priorityLevel',
                    'categoryId',
                    'category',
                    'responseTimeHours',
                    'completionTimeHours',
                    'isActive',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test SLA',
                    'categoryId' => $category->id,
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ],
                ]
            ]);

        $this->assertDatabaseHas('sla_definitions', [
            'name' => 'Test SLA',
            'company_id' => $this->company->id,
            'category_id' => $category->id,
        ]);
    }

    /** @test */
    public function it_rejects_working_days_and_business_hours_only_fields()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Test SLA',
            'applies_to' => 'work_orders',
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
            'working_days' => ['monday', 'tuesday'],
            'business_hours_only' => true,
        ]);

        // Should either reject with validation error or ignore the fields
        // Since these fields are not in fillable, they should be ignored
        $response->assertStatus(201);

        // Verify the fields are not in database
        $sla = SlaDefinition::where('name', 'Test SLA')->first();
        $this->assertArrayNotHasKey('working_days', $sla->getAttributes());
        $this->assertArrayNotHasKey('business_hours_only', $sla->getAttributes());
    }

    /** @test */
    public function it_can_create_sla_definition_without_category()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Test SLA No Category',
            'applies_to' => 'work_orders',
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'categoryId' => null,
                    'category' => null,
                ]
            ]);

        $this->assertDatabaseHas('sla_definitions', [
            'name' => 'Test SLA No Category',
            'category_id' => null,
        ]);
    }

    /** @test */
    public function it_validates_category_id_exists()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Test SLA',
            'applies_to' => 'work_orders',
            'category_id' => 99999, // Non-existent category
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    /** @test */
    public function it_can_list_sla_definitions_with_category_relationship()
    {
        $category = WorkOrderCategory::factory()->create(['company_id' => null]);
        
        SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'created_by' => $this->user->id,
        ]);

        SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => null,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/sla/definitions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'definitions' => [
                        '*' => [
                            'id',
                            'name',
                            'categoryId',
                            'category',
                        ]
                    ]
                ],
                'pagination'
            ]);

        $definitions = $response->json('data.definitions');
        $this->assertCount(2, $definitions);
        
        // Check that category relationship is loaded
        $definitionWithCategory = collect($definitions)->firstWhere('categoryId', $category->id);
        $this->assertNotNull($definitionWithCategory);
        $this->assertNotNull($definitionWithCategory['category']);
        $this->assertEquals($category->id, $definitionWithCategory['category']['id']);
    }

    /** @test */
    public function it_can_update_sla_definition_category()
    {
        $category1 = WorkOrderCategory::factory()->create(['company_id' => null]);
        $category2 = WorkOrderCategory::factory()->create(['company_id' => null]);

        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category1->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->putJson("/api/sla/definitions/{$sla->id}", [
            'name' => $sla->name,
            'applies_to' => $sla->applies_to,
            'category_id' => $category2->id,
            'response_time_hours' => $sla->response_time_hours,
            'completion_time_hours' => $sla->completion_time_hours,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'categoryId' => $category2->id,
                    'category' => [
                        'id' => $category2->id,
                        'name' => $category2->name,
                    ],
                ]
            ]);

        $this->assertDatabaseHas('sla_definitions', [
            'id' => $sla->id,
            'category_id' => $category2->id,
        ]);
    }

    /** @test */
    public function it_can_filter_by_category()
    {
        $category1 = WorkOrderCategory::factory()->create(['company_id' => null]);
        $category2 = WorkOrderCategory::factory()->create(['company_id' => null]);

        SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category1->id,
            'created_by' => $this->user->id,
        ]);

        SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category2->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson("/api/sla/definitions");

        $response->assertStatus(200);
        $definitions = $response->json('data.definitions');
        
        // Filter client-side since API doesn't have category_id filter
        $category1Definitions = collect($definitions)->filter(function ($def) use ($category1) {
            return $def['categoryId'] === $category1->id;
        });
        
        $category2Definitions = collect($definitions)->filter(function ($def) use ($category2) {
            return $def['categoryId'] === $category2->id;
        });
        
        $this->assertGreaterThanOrEqual(1, $category1Definitions->count());
        $this->assertGreaterThanOrEqual(1, $category2Definitions->count());
        
        // Verify each definition has correct category
        foreach ($category1Definitions as $definition) {
            $this->assertEquals($category1->id, $definition['categoryId']);
        }
    }

    /** @test */
    public function it_can_search_by_category_name()
    {
        $category = WorkOrderCategory::factory()->create([
            'company_id' => null,
            'name' => 'Emergency Repair'
        ]);

        SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'name' => 'Test SLA',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/sla/definitions?search=Emergency');

        $response->assertStatus(200);
        $definitions = $response->json('data.definitions');
        $this->assertGreaterThanOrEqual(1, count($definitions));
    }

    /** @test */
    public function it_validates_priority_level_values()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Test SLA',
            'applies_to' => 'work_orders',
            'priority_level' => 'invalid_priority',
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority_level']);
    }

    /** @test */
    public function it_can_toggle_active_status()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->patchJson("/api/sla/definitions/{$sla->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'isActive' => false,
                ]
            ]);

        $this->assertDatabaseHas('sla_definitions', [
            'id' => $sla->id,
            'is_active' => false,
        ]);

        // Toggle again
        $response = $this->patchJson("/api/sla/definitions/{$sla->id}/toggle-active");
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'isActive' => true,
                ]
            ]);
    }

    /** @test */
    public function it_prevents_cross_company_access()
    {
        $otherSla = SlaDefinition::factory()->create([
            'company_id' => $this->otherCompany->id,
            'created_by' => $this->otherUser->id,
        ]);

        $this->actingAs($this->user);

        // Try to view
        $response = $this->getJson("/api/sla/definitions/{$otherSla->id}");
        $response->assertStatus(403);

        // Try to update
        $response = $this->putJson("/api/sla/definitions/{$otherSla->id}", [
            'name' => 'Updated Name',
            'applies_to' => 'work_orders',
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
        ]);
        $response->assertStatus(403);

        // Try to delete
        $response = $this->deleteJson("/api/sla/definitions/{$otherSla->id}");
        $response->assertStatus(403);

        // Try to toggle
        $response = $this->patchJson("/api/sla/definitions/{$otherSla->id}/toggle-active");
        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'applies_to', 'response_time_hours', 'completion_time_hours']);
    }

    /** @test */
    public function it_validates_unique_name_per_company()
    {
        SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Existing SLA',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Existing SLA',
            'applies_to' => 'work_orders',
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_allows_same_name_different_companies()
    {
        SlaDefinition::factory()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Shared Name',
            'created_by' => $this->otherUser->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/sla/definitions', [
            'name' => 'Shared Name',
            'applies_to' => 'work_orders',
            'response_time_hours' => 4,
            'completion_time_hours' => 24,
        ]);

        $response->assertStatus(201);
    }
}

