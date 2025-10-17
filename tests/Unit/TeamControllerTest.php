<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Api\TeamController;
use App\Services\TeamCacheService;
use App\Services\TeamAuditService;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $cacheService;
    protected $auditService;
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = Mockery::mock(TeamCacheService::class);
        $this->auditService = Mockery::mock(TeamAuditService::class);
        $this->controller = new TeamController($this->cacheService, $this->auditService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_list_team_members()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'admin'
        ]);
        $role = Role::factory()->create(['company_id' => $company->id]);

        // Create some team members
        $teamMembers = User::factory()->count(5)->create([
            'company_id' => $company->id,
            'user_type' => 'team'
        ]);

        foreach ($teamMembers as $member) {
            $member->roles()->attach($role);
        }

        $this->actingAs($user);

        $response = $this->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'teams' => [
                        '*' => ['id', 'first_name', 'last_name', 'email', 'user_type']
                    ],
                    'pagination',
                    'filters',
                    'sorting'
                ]
            ]);
    }

    /** @test */
    public function it_can_create_team_member()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'admin'
        ]);
        $role = Role::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        // Mock audit and cache services
        $this->auditService->shouldReceive('logCreated')->once();
        $this->cacheService->shouldReceive('clearCompanyCache')->once();

        $response = $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'role_id' => $role->id,
            'hourly_rate' => 50
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'first_name', 'last_name', 'email']
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'user_type' => 'team',
            'company_id' => $company->id
        ]);
    }

    /** @test */
    public function it_can_update_team_member()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'admin'
        ]);
        $role = Role::factory()->create(['company_id' => $company->id]);
        
        $teamMember = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'team',
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);
        $teamMember->roles()->attach($role);

        $this->actingAs($user);

        // Mock audit and cache services
        $this->auditService->shouldReceive('logUpdated')->once();
        $this->cacheService->shouldReceive('clearCompanyCache')->once();

        $response = $this->putJson("/api/teams/{$teamMember->id}", [
            'first_name' => 'Janet',
            'hourly_rate' => 60
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Team member updated successfully'
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $teamMember->id,
            'first_name' => 'Janet',
            'hourly_rate' => 60
        ]);
    }

    /** @test */
    public function it_can_delete_team_member()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'admin'
        ]);
        
        $teamMember = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'team'
        ]);

        $this->actingAs($user);

        // Mock audit and cache services
        $this->auditService->shouldReceive('logDeleted')->once();
        $this->cacheService->shouldReceive('clearCompanyCache')->once();

        $response = $this->deleteJson("/api/teams/{$teamMember->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Team member removed successfully'
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $teamMember->id
        ]);
    }

    /** @test */
    public function it_returns_statistics()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id
        ]);

        $this->actingAs($user);

        // Mock cache service
        $this->cacheService->shouldReceive('getStatistics')
            ->once()
            ->with($company->id)
            ->andReturn([
                'total_team_members' => 10,
                'active_team_members' => 8,
                'pending_team_members' => 2,
                'assigned_work_orders_total_count' => 50,
                'assigned_work_orders_active_count' => 30,
                'assigned_work_orders_completed_count' => 20,
                'completion_rate' => 40.0
            ]);

        $response = $this->getJson('/api/teams/statistics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_team_members' => 10,
                    'active_team_members' => 8
                ]
            ]);
    }

    /** @test */
    public function it_returns_analytics()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id
        ]);

        $this->actingAs($user);

        // Mock cache service
        $this->cacheService->shouldReceive('getAnalytics')
            ->once()
            ->with($company->id, 30)
            ->andReturn([
                'date_range_days' => 30,
                'productivity_rate_percent' => 85.5,
                'on_time_rate_percent' => 90.0,
                'avg_completion_days' => 5.2,
                'labor_cost_total' => 15000.00,
                'top_performers' => []
            ]);

        $response = $this->getJson('/api/teams/analytics?date_range=30');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'date_range_days' => 30,
                    'productivity_rate_percent' => 85.5
                ]
            ]);
    }

    /** @test */
    public function it_cannot_access_team_members_from_different_company()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $teamMemberB = User::factory()->create([
            'company_id' => $companyB->id,
            'user_type' => 'team'
        ]);

        $this->actingAs($userA);

        $response = $this->getJson("/api/teams/{$teamMemberB->id}");

        $response->assertStatus(404);
    }
}

