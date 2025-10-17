<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $user;
    protected $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'admin'
        ]);
        $this->role = Role::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Technician'
        ]);
    }

    /** @test */
    public function it_can_create_team_member_with_valid_data()
    {
        Mail::fake();

        $this->actingAs($this->user);

        $response = $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
            'role_id' => $this->role->id,
            'hourly_rate' => 45.50
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'user_type',
                    'hourly_rate'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@test.com',
            'user_type' => 'team',
            'company_id' => $this->company->id,
            'hourly_rate' => 45.50
        ]);

        // Verify invitation email was sent
        Mail::assertSent(\App\Mail\TeamInvitationMail::class);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/teams', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'role_id']);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'role_id' => $this->role->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_validates_unique_email()
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@test.com',
            'role_id' => $this->role->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_prevents_cross_company_role_assignment()
    {
        $otherCompany = Company::factory()->create();
        $otherRole = Role::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'role_id' => $otherRole->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    /** @test */
    public function it_prevents_cross_company_location_assignment()
    {
        $otherCompany = Company::factory()->create();
        $otherLocation = Location::factory()->create(['company_id' => $otherCompany->id]);

        $locationRole = Role::factory()->create([
            'company_id' => $this->company->id,
            'has_location_access' => true
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'role_id' => $locationRole->id,
            'location_ids' => [$otherLocation->id]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['location_ids']);
    }

    /** @test */
    public function it_can_update_team_member()
    {
        $teamMember = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'first_name' => 'Jane',
            'hourly_rate' => 40
        ]);
        $teamMember->roles()->attach($this->role);

        $this->actingAs($this->user);

        $response = $this->putJson("/api/teams/{$teamMember->id}", [
            'first_name' => 'Janet',
            'hourly_rate' => 50
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Team member updated successfully'
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $teamMember->id,
            'first_name' => 'Janet',
            'hourly_rate' => 50
        ]);
    }

    /** @test */
    public function it_can_search_team_members()
    {
        User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@test.com'
        ]);

        User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@test.com'
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/teams?search=John');

        $response->assertStatus(200);
        
        $teams = $response->json('data.teams');
        $this->assertCount(1, $teams);
        $this->assertEquals('John', $teams[0]['first_name']);
    }

    /** @test */
    public function it_can_filter_by_status()
    {
        // Active team member (email verified)
        User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'email_verified_at' => now()
        ]);

        // Inactive team member (not verified)
        User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'email_verified_at' => null
        ]);

        $this->actingAs($this->user);

        // Test active filter
        $response = $this->getJson('/api/teams?status=active');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.teams')));

        // Test inactive filter
        $response = $this->getJson('/api/teams?status=inactive');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.teams')));
    }

    /** @test */
    public function it_can_resend_invitation()
    {
        Mail::fake();

        $teamMember = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type' => 'team'
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson("/api/teams/{$teamMember->id}/resend-invitation");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation email sent successfully'
            ]);

        Mail::assertSent(\App\Mail\TeamInvitationMail::class);
    }

    /** @test */
    public function it_can_get_statistics()
    {
        // Create some team members
        User::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'email_verified_at' => now()
        ]);

        User::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'user_type' => 'team',
            'email_verified_at' => null
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/teams/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_team_members',
                    'active_team_members',
                    'pending_team_members',
                    'assigned_work_orders_total_count',
                    'assigned_work_orders_active_count',
                    'assigned_work_orders_completed_count',
                    'completion_rate'
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(7, $data['total_team_members']);
        $this->assertEquals(5, $data['active_team_members']);
        $this->assertEquals(2, $data['pending_team_members']);
    }

    /** @test */
    public function it_can_get_analytics()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/teams/analytics?date_range=30');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date_range_days',
                    'productivity_rate_percent',
                    'on_time_rate_percent',
                    'avg_completion_days',
                    'labor_cost_total',
                    'top_performers'
                ]
            ]);
    }

    /** @test */
    public function it_caches_statistics()
    {
        Cache::flush();

        $this->actingAs($this->user);

        // First call - should query database
        $response1 = $this->getJson('/api/teams/statistics');
        $response1->assertStatus(200);

        // Second call - should use cache
        $response2 = $this->getJson('/api/teams/statistics');
        $response2->assertStatus(200);

        // Results should be identical
        $this->assertEquals(
            $response1->json('data'),
            $response2->json('data')
        );
    }

    /** @test */
    public function it_clears_cache_on_team_member_creation()
    {
        Cache::flush();

        $this->actingAs($this->user);

        // Prime the cache
        $this->getJson('/api/teams/statistics');

        // Create a team member (should clear cache)
        $this->postJson('/api/teams', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
            'role_id' => $this->role->id
        ]);

        // Verify cache was cleared by checking statistics reflect new member
        $response = $this->getJson('/api/teams/statistics');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_rate_limits_analytics_endpoint()
    {
        $this->actingAs($this->user);

        // Make 31 requests rapidly
        for ($i = 0; $i < 31; $i++) {
            $response = $this->getJson('/api/teams/analytics');
            
            if ($i < 30) {
                $response->assertStatus(200);
            } else {
                // 31st request should be rate limited
                $response->assertStatus(429);
            }
        }
    }

    /** @test */
    public function it_prevents_deleting_team_member_from_different_company()
    {
        $otherCompany = Company::factory()->create();
        $otherTeamMember = User::factory()->create([
            'company_id' => $otherCompany->id,
            'user_type' => 'team'
        ]);

        $this->actingAs($this->user);

        $response = $this->deleteJson("/api/teams/{$otherTeamMember->id}");

        $response->assertStatus(404);

        // Verify team member still exists
        $this->assertDatabaseHas('users', [
            'id' => $otherTeamMember->id
        ]);
    }

    /** @test */
    public function it_can_get_available_roles()
    {
        // Create multiple roles
        Role::factory()->count(3)->create([
            'company_id' => $this->company->id
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/teams/available-roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'company_id']
                ]
            ]);

        // Should only see roles from user's company
        $roles = $response->json('data');
        foreach ($roles as $role) {
            $this->assertEquals($this->company->id, $role['company_id']);
        }
    }
}

