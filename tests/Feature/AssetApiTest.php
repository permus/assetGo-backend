<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetTag;
use App\Models\Location;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_create_asset()
    {
        $category = AssetCategory::factory()->create();
        $tag = AssetTag::factory()->create();
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        $data = [
            'name' => 'Test Asset',
            'serial_number' => 'SN123456',
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'tags' => [$tag->id],
            'location_id' => $location->id,
            'images' => [UploadedFile::fake()->image('asset.jpg')],
        ];
        $response = $this->actingAs($this->user)->postJson('/api/assets', $data);
        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('assets', ['name' => 'Test Asset', 'serial_number' => 'SN123456']);
    }

    public function test_can_update_asset()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $data = ['name' => 'Updated Asset'];
        $response = $this->actingAs($this->user)->putJson('/api/assets/' . $asset->id, $data);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Updated Asset']);
    }

    public function test_can_delete_asset()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->deleteJson('/api/assets/' . $asset->id);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_can_show_asset()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->getJson('/api/assets/' . $asset->id);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'data' => ['asset']]);
    }

    public function test_can_list_assets()
    {
        Asset::factory()->count(3)->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->getJson('/api/assets');
        $response->assertStatus(200)->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'data' => ['assets', 'pagination', 'filters']]);
    }

    public function test_can_duplicate_asset()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id, 'serial_number' => 'SN1']);
        $data = ['serial_number' => 'SN2'];
        $response = $this->actingAs($this->user)->postJson('/api/assets/' . $asset->id . '/duplicate', $data);
        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('assets', ['serial_number' => 'SN2']);
    }

    public function test_can_transfer_asset()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        $data = [
            'to_location_id' => $location->id,
            'transfer_date' => now()->toDateString(),
        ];
        $response = $this->actingAs($this->user)->postJson('/api/assets/' . $asset->id . '/transfer', $data);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'location_id' => $location->id]);
    }

    public function test_can_bulk_import_assets()
    {
        $csv = "name,serial_number,company_id\nAsset1,SN1,{$this->company->id}\nAsset2,SN2,{$this->company->id}";
        $file = UploadedFile::fake()->createWithContent('assets.csv', $csv);
        $response = $this->actingAs($this->user)->postJson('/api/assets/import/bulk', ['file' => $file]);
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('assets', ['serial_number' => 'SN1']);
        $this->assertDatabaseHas('assets', ['serial_number' => 'SN2']);
    }

    public function test_can_manage_maintenance_schedules()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $data = [
            'schedule_type' => 'inspection',
            'next_due' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ];
        // Add
        $add = $this->actingAs($this->user)->postJson('/api/assets/' . $asset->id . '/maintenance-schedules', $data);
        $add->assertStatus(201)->assertJson(['success' => true]);
        $scheduleId = $add->json('data.id');
        // Update
        $update = $this->actingAs($this->user)->putJson('/api/assets/' . $asset->id . '/maintenance-schedules/' . $scheduleId, ['status' => 'inactive']);
        $update->assertStatus(200)->assertJson(['success' => true]);
        // List
        $list = $this->actingAs($this->user)->getJson('/api/assets/' . $asset->id . '/maintenance-schedules');
        $list->assertStatus(200)->assertJson(['success' => true]);
        // Delete
        $delete = $this->actingAs($this->user)->deleteJson('/api/assets/' . $asset->id . '/maintenance-schedules/' . $scheduleId);
        $delete->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_can_get_activity_history()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->getJson('/api/assets/' . $asset->id . '/activity-history');
        $response->assertStatus(200)->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'data']);
    }
} 