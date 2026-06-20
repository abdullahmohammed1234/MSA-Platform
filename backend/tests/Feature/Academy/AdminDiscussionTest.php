<?php

namespace Tests\Feature\Academy;

use App\Models\DiscussionCategory;
use App\Models\DiscussionThread;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDiscussionTest extends TestCase
{
    use RefreshDatabase;

    protected function adminWithPermission(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'manage_discussions'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Manage Discussions',
                'module' => 'Academy',
                'description' => 'Moderate forum threads.',
            ]
        );

        $role = Role::firstOrCreate(
            ['slug' => 'moderator'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Moderator',
                'description' => 'Forum moderator',
            ]
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_admin_can_list_discussion_threads(): void
    {
        $admin = $this->adminWithPermission();
        $author = User::factory()->create();
        $category = DiscussionCategory::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        DiscussionThread::create([
            'user_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Test thread',
            'content' => 'Sample content for moderation.',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/academy/discussions/threads')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['discussions' => [['id', 'title', 'authorName']]]);
    }

    public function test_unauthorized_user_cannot_access_moderation_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/admin/academy/discussions/threads')
            ->assertStatus(403);
    }
}
