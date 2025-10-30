<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['owner_id' => $this->user->id]);
        $this->user->update(['company_id' => $this->company->id]);
    }

    public function test_can_filter_schedules_by_plan_type()
    {
        // Create maintenance plans with different plan types
        $preventivePlan = MaintenancePlan::factory()->create([
            'company_id' => $this->company->id,
            'plan_type' => 'preventive'
        ]);
        
        $predictivePlan = MaintenancePlan::factory()->create([
            'company_id' => $this->company->id,
            'plan_type' => 'predictive'
        ]);

        $conditionBasedPlan = MaintenancePlan::factory()->create([
            'company_id' => $this->company->id,
            'plan_type' => 'condition_based'
        ]);

        // Create schedules for each plan type
        ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $preventivePlan->id,
            'status' => 'scheduled'
        ]);

        ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $predictivePlan->id,
            'status' => 'scheduled'
        ]);

        ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $conditionBasedPlan->id,
            'status' => 'scheduled'
        ]);

        // Test filtering by preventive plan type
        $response = $this->actingAs($this->user)
            ->getJson('/api/maintenance/schedules?plan_type=preventive');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');

        // Test filtering by predictive plan type
        $response = $this->actingAs($this->user)
            ->getJson('/api/maintenance/schedules?plan_type=predictive');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');

        // Test filtering by condition_based plan type
        $response = $this->actingAs($this->user)
            ->getJson('/api/maintenance/schedules?plan_type=condition_based');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');

        // Test with invalid plan type (should return all schedules)
        $response = $this->actingAs($this->user)
            ->getJson('/api/maintenance/schedules?plan_type=invalid_type');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data'); // Should return all schedules
    }

    public function test_schedule_response_includes_plan_type()
    {
        $plan = MaintenancePlan::factory()->create([
            'company_id' => $this->company->id,
            'plan_type' => 'preventive'
        ]);

        ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $plan->id,
            'status' => 'scheduled'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/maintenance/schedules');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'maintenance_plan_id',
                        'plan_type',
                        'asset_ids',
                        'start_date',
                        'due_date',
                        'status',
                        'priority_id',
                        'priority_name',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);

        // Verify plan_type and priority_name are included in response
        $data = $response->json('data');
        $this->assertArrayHasKey('plan_type', $data[0]);
        $this->assertArrayHasKey('priority_name', $data[0]);
        $this->assertEquals('preventive', $data[0]['plan_type']);
    }
}
