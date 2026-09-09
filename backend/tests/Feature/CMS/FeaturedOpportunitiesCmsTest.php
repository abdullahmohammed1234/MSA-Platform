<?php

namespace Tests\Feature\CMS;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\CMS\FeaturedOpportunity;
use App\Models\CMS\CmsRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeaturedOpportunitiesCmsTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'System administrator',
        ]);

        $perm = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manage Featured Opportunities',
            'slug' => 'manage_featured_opportunities',
            'module' => 'Website',
            'description' => 'Create and manage featured community opportunities',
        ]);
        $adminRole->permissions()->attach($perm);

        $this->adminUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->normalUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Normal User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function guests_and_unauthorized_users_cannot_manage_featured_opportunities()
    {
        $this->getJson(route('api.admin.cms.featured-opportunities.index'))
            ->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->getJson(route('api.admin.cms.featured-opportunities.index'))
            ->assertStatus(403);

        $this->actingAs($this->normalUser)
            ->postJson(route('api.admin.cms.featured-opportunities.store'), [
                'title' => 'Unauthorized Opportunity',
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_featured_opportunity_with_features_and_auto_slug()
    {
        $data = [
            'title' => 'The Blessed Tree — Year One Program',
            'eyebrow' => 'Featured Educational Resource',
            'short_description' => 'Deepen your spiritual foundation.',
            'description' => 'Full description of Year One program.',
            'featured_image' => '/Hero/blessed_tree.webp',
            'external_url' => 'https://theblessedtree.org/programs/year-one',
            'features' => [
                'Structured Year-Long Curriculum',
                'Accessible Post-Secondary Schedule'
            ],
            'is_published' => true,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.featured-opportunities.store'), $data)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('featured_opportunities', [
            'title' => 'The Blessed Tree — Year One Program',
            'slug' => 'the-blessed-tree-year-one-program',
            'eyebrow' => 'Featured Educational Resource',
            'is_published' => true,
        ]);

        $opportunity = FeaturedOpportunity::first();
        $this->assertEquals(['Structured Year-Long Curriculum', 'Accessible Post-Secondary Schedule'], $opportunity->features);

        $this->assertDatabaseHas('cms_revisions', [
            'revisable_type' => FeaturedOpportunity::class,
            'revisable_id' => $opportunity->id,
            'version' => 1,
        ]);
    }

    /** @test */
    public function slug_collisions_are_handled_automatically()
    {
        FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Arabic Program',
            'slug' => 'arabic-program',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.featured-opportunities.store'), [
                'title' => 'Arabic Program',
                'is_published' => true,
            ])
            ->assertStatus(201);

        $this->assertEquals('arabic-program-1', $response->json('opportunity.slug'));
    }

    /** @test */
    public function draft_opportunities_are_excluded_from_public_endpoint()
    {
        FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Draft Opportunity',
            'slug' => 'draft-opportunity',
            'is_published' => false,
            'sort_order' => 1,
        ]);

        FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Published Opportunity',
            'slug' => 'published-opportunity',
            'is_published' => true,
            'published_at' => now(),
            'sort_order' => 2,
        ]);

        $response = $this->getJson(route('api.website.featured-opportunities'))
            ->assertStatus(200);

        $opportunities = $response->json('opportunities');
        $this->assertCount(1, $opportunities);
        $this->assertEquals('Published Opportunity', $opportunities[0]['title']);
    }

    /** @test */
    public function admin_can_reorder_featured_opportunities()
    {
        $opp1 = FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'First Opportunity',
            'slug' => 'first-opportunity',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $opp2 = FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Second Opportunity',
            'slug' => 'second-opportunity',
            'is_published' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.featured-opportunities.reorder'), [
                'uuids' => [$opp2->uuid, $opp1->uuid]
            ])
            ->assertStatus(200);

        $this->assertEquals(1, $opp2->fresh()->sort_order);
        $this->assertEquals(2, $opp1->fresh()->sort_order);
    }

    /** @test */
    public function admin_can_rollback_featured_opportunity()
    {
        $opp = FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Original Title',
            'slug' => 'original-title',
            'description' => 'Original Description',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        CmsRevision::create([
            'revisable_type' => FeaturedOpportunity::class,
            'revisable_id' => $opp->id,
            'user_id' => $this->adminUser->id,
            'content' => [
                'title' => 'Original Title',
                'slug' => 'original-title',
                'description' => 'Original Description',
                'is_published' => true,
            ],
            'version' => 1,
        ]);

        $opp->update([
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);

        CmsRevision::create([
            'revisable_type' => FeaturedOpportunity::class,
            'revisable_id' => $opp->id,
            'user_id' => $this->adminUser->id,
            'content' => [
                'title' => 'Updated Title',
                'slug' => 'updated-title',
                'description' => 'Updated Description',
                'is_published' => true,
            ],
            'version' => 2,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.featured-opportunities.rollback', $opp->uuid), ['version' => 1])
            ->assertStatus(200);

        $this->assertEquals('Original Title', $opp->fresh()->title);
        $this->assertEquals('Original Description', $opp->fresh()->description);
    }

    /** @test */
    public function admin_can_soft_delete_featured_opportunity()
    {
        $opp = FeaturedOpportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Opportunity to Delete',
            'slug' => 'opportunity-to-delete',
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson(route('api.admin.cms.featured-opportunities.destroy', $opp->uuid))
            ->assertStatus(200);

        $this->assertSoftDeleted('featured_opportunities', [
            'uuid' => $opp->uuid,
        ]);
    }

    /** @test */
    public function cms_prefix_routes_are_accessible()
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('api.cms.featured-opportunities.index'))
            ->assertStatus(200);
    }
}
