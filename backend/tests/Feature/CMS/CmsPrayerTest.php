<?php

namespace Tests\Feature\CMS;

use App\Models\CMS\CmsPrayer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CmsPrayerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $user->roles()->sync([$role->id]);

        \Illuminate\Support\Facades\DB::table('application_access')->insertOrIgnore([
            'user_id' => $user->id,
            'application' => 'cms',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_public_can_fetch_cms_prayers(): void
    {
        CmsPrayer::create([
            'type' => 'jumuah',
            'title' => "Burnaby 1st Jumu'ah",
            'campus' => 'Burnaby',
            'location' => 'Educational Gym',
            'khutbah_time' => '1:30 PM',
            'prayer_time' => '2:00 PM',
            'is_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/cms/prayers');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data.jumuah');
        $response->assertJsonPath('data.jumuah.0.title', "Burnaby 1st Jumu'ah");
    }

    public function test_admin_can_create_and_update_cms_prayer_with_cache_invalidation(): void
    {
        $admin = $this->adminUser();

        $createResponse = $this->actingAs($admin)->postJson('/api/v1/admin/cms/prayers', [
            'type' => 'jumuah',
            'title' => "Surrey Jumu'ah",
            'campus' => 'Surrey',
            'location' => 'SRYE 1005',
            'khutbah_time' => '1:15 PM',
            'prayer_time' => '1:45 PM',
            'is_enabled' => true,
        ]);

        $createResponse->assertCreated();
        $prayerId = $createResponse->json('data.id');

        $this->assertDatabaseHas('cms_prayers', [
            'id' => $prayerId,
            'title' => "Surrey Jumu'ah",
        ]);

        // Public endpoint returns cached result
        $publicRes1 = $this->getJson('/api/v1/cms/prayers');
        $publicRes1->assertOk();
        $publicRes1->assertJsonPath('data.jumuah.0.khutbah_time', '1:15 PM');

        // Admin updates khutbah time
        $updateResponse = $this->actingAs($admin)->putJson("/api/v1/admin/cms/prayers/{$prayerId}", [
            'khutbah_time' => '1:30 PM',
        ]);
        $updateResponse->assertOk();

        // Public endpoint returns new value after cache invalidation
        $publicRes2 = $this->getJson('/api/v1/cms/prayers');
        $publicRes2->assertOk();
        $publicRes2->assertJsonPath('data.jumuah.0.khutbah_time', '1:30 PM');
    }

    public function test_guests_cannot_manage_cms_prayers(): void
    {
        $response = $this->postJson('/api/v1/admin/cms/prayers', [
            'type' => 'jumuah',
            'title' => 'Unauthorized Jumuah',
        ]);

        $response->assertUnauthorized();
    }
}
