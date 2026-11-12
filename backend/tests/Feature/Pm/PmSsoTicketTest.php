<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\SsoTicket;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Services\DepartmentMembershipService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmSsoTicketTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;
    protected Department $itDept;
    protected User $eligibleUser;
    protected User $ineligibleUser;
    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Member']);

        // Eligible user: has member role, active, IT member, granted 'projects' access
        $this->eligibleUser = User::factory()->create([
            'name' => 'Eligible PMS User',
            'email' => 'eligible@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->eligibleUser->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->eligibleUser, DepartmentRole::Member);
        $this->appAccessService->grant($this->eligibleUser, 'projects', $this->eligibleUser);

        // Ineligible user: no PMS application access
        $this->ineligibleUser = User::factory()->create([
            'name' => 'Ineligible User',
            'email' => 'ineligible@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->ineligibleUser->roles()->sync([$memberRole->id]);

        // Inactive user
        $this->inactiveUser = User::factory()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@sfumsa.ca',
            'is_active' => false,
        ]);
        $this->inactiveUser->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->inactiveUser, DepartmentRole::Member);
        $this->appAccessService->grant($this->inactiveUser, 'projects', $this->inactiveUser);
    }

    public function test_authenticated_eligible_user_can_request_sso_ticket(): void
    {
        Sanctum::actingAs($this->eligibleUser);

        $response = $this->postJson('/api/v1/sso/tickets', [
            'service' => 'pms',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'ticket',
            'service',
            'expires_in',
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('pms', $response->json('service'));
        $this->assertEquals(60, $response->json('expires_in'));

        $plainTicket = $response->json('ticket');
        $this->assertEquals(64, strlen($plainTicket));

        // Verify ticket hash in database
        $expectedHash = hash('sha256', $plainTicket);
        $this->assertDatabaseHas('sso_tickets', [
            'user_id' => $this->eligibleUser->id,
            'service' => 'pms',
            'ticket_hash' => $expectedHash,
            'consumed_at' => null,
        ]);

        // Plaintext ticket must NOT be in the database
        $this->assertDatabaseMissing('sso_tickets', [
            'ticket_hash' => $plainTicket,
        ]);
    }

    public function test_unauthenticated_user_cannot_request_sso_ticket(): void
    {
        $response = $this->postJson('/api/v1/sso/tickets', [
            'service' => 'pms',
        ]);

        $response->assertStatus(401);
    }

    public function test_request_sso_ticket_rejects_unsupported_service(): void
    {
        Sanctum::actingAs($this->eligibleUser);

        $response = $this->postJson('/api/v1/sso/tickets', [
            'service' => 'unsupported_service_xyz',
        ]);

        $response->assertStatus(422);
    }

    public function test_inactive_user_cannot_request_sso_ticket(): void
    {
        Sanctum::actingAs($this->inactiveUser);

        $response = $this->postJson('/api/v1/sso/tickets', [
            'service' => 'pms',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => \App\Http\Middleware\Authenticate::DEACTIVATED_MESSAGE]);
    }

    public function test_user_without_pms_access_cannot_request_sso_ticket(): void
    {
        Sanctum::actingAs($this->ineligibleUser);

        $response = $this->postJson('/api/v1/sso/tickets', [
            'service' => 'pms',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'User is not eligible to access the Project Management System.']);
    }

    public function test_valid_sso_ticket_can_be_exchanged_for_pms_sanctum_token(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        // Unauthenticated exchange endpoint
        $response = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'token',
            'token_type',
            'user' => ['id', 'name', 'email'],
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('Bearer', $response->json('token_type'));
        $this->assertEquals($this->eligibleUser->id, $response->json('user.id'));

        // Verify ticket is marked consumed
        $expectedHash = hash('sha256', $plainTicket);
        $ticketRecord = SsoTicket::where('ticket_hash', $expectedHash)->first();
        $this->assertNotNull($ticketRecord);
        $this->assertNotNull($ticketRecord->consumed_at);

        // Verify issued token works to authenticate PMS API requests
        $pmsToken = $response->json('token');
        $apiCheck = $this->withHeader('Authorization', 'Bearer ' . $pmsToken)
            ->getJson('/api/v1/pm/projects');

        $apiCheck->assertStatus(200);
    }

    public function test_reusing_consumed_ticket_is_rejected(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        // First exchange succeeds
        $firstExchange = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);
        $firstExchange->assertStatus(200);

        // Second exchange fails
        $secondExchange = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);
        $secondExchange->assertStatus(401);
        $secondExchange->assertJsonFragment(['message' => 'SSO ticket has already been used.']);
    }

    public function test_expired_ticket_is_rejected(): void
    {
        $plainTicket = bin2hex(random_bytes(32));
        $ticketHash = hash('sha256', $plainTicket);

        // Create expired ticket directly in database
        SsoTicket::create([
            'ticket_hash' => $ticketHash,
            'user_id' => $this->eligibleUser->id,
            'service' => 'pms',
            'expires_at' => now()->subMinutes(5),
            'consumed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'SSO ticket has expired.']);
    }

    public function test_ticket_for_wrong_audience_service_is_rejected(): void
    {
        $plainTicket = bin2hex(random_bytes(32));
        $ticketHash = hash('sha256', $plainTicket);

        // Ticket created for a different service
        SsoTicket::create([
            'ticket_hash' => $ticketHash,
            'user_id' => $this->eligibleUser->id,
            'service' => 'other_app',
            'expires_at' => now()->addMinutes(1),
            'consumed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'SSO ticket audience mismatch.']);
    }

    public function test_malformed_ticket_is_rejected(): void
    {
        // Malformed: too short (< 32 characters)
        $shortResponse = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => 'tooshort',
            'service' => 'pms',
        ]);
        $shortResponse->assertStatus(422);

        // Malformed: random 64-char string that does not exist
        $fakeTicket = bin2hex(random_bytes(32));
        $unknownResponse = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $fakeTicket,
            'service' => 'pms',
        ]);
        $unknownResponse->assertStatus(401);
        $unknownResponse->assertJsonFragment(['message' => 'Invalid or malformed SSO ticket.']);
    }

    public function test_inactive_user_cannot_exchange_ticket(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        // Deactivate user after ticket issuance
        $this->eligibleUser->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'User account is inactive or disabled.']);
    }

    public function test_user_without_pms_access_cannot_exchange_ticket(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        // Revoke PMS access after ticket issuance
        $this->appAccessService->revoke($this->eligibleUser, 'projects', $this->eligibleUser);

        $response = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'User does not possess Project Management access privileges.']);
    }

    public function test_main_website_sanctum_token_is_never_returned(): void
    {
        // Create an explicit main website token
        $mainSiteToken = $this->eligibleUser->createToken('main_site_session')->plainTextToken;

        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        $response = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);

        $response->assertStatus(200);
        $issuedToken = $response->json('token');

        // The newly issued token must never match the caller's main site token
        $this->assertNotEquals($mainSiteToken, $issuedToken);
    }

    public function test_clean_sso_tickets_command_prunes_expired_and_old_consumed_tickets_safely(): void
    {
        // 1. Active unconsumed ticket (MUST NOT BE DELETED)
        $activeTicket = SsoTicket::create([
            'ticket_hash' => hash('sha256', 'active_ticket_xyz'),
            'user_id' => $this->eligibleUser->id,
            'service' => 'pms',
            'expires_at' => now()->addSeconds(50),
            'consumed_at' => null,
        ]);

        // 2. Expired ticket (MUST BE DELETED)
        $expiredTicket = SsoTicket::create([
            'ticket_hash' => hash('sha256', 'expired_ticket_xyz'),
            'user_id' => $this->eligibleUser->id,
            'service' => 'pms',
            'expires_at' => now()->subMinutes(5),
            'consumed_at' => null,
        ]);

        // 3. Recently consumed ticket (<24 hours old) (MUST BE PRESERVED FOR AUDIT)
        $recentConsumedTicket = SsoTicket::create([
            'ticket_hash' => hash('sha256', 'recent_consumed_ticket_xyz'),
            'user_id' => $this->eligibleUser->id,
            'service' => 'pms',
            'expires_at' => now()->addMinutes(1),
            'consumed_at' => now()->subMinutes(10),
        ]);

        // 4. Old consumed ticket (>24 hours old) (MUST BE DELETED)
        $oldConsumedTicket = SsoTicket::create([
            'ticket_hash' => hash('sha256', 'old_consumed_ticket_xyz'),
            'user_id' => $this->eligibleUser->id,
            'service' => 'pms',
            'expires_at' => now()->subDays(2),
            'consumed_at' => now()->subDays(2),
        ]);

        // Run artisan command
        $exitCode = \Illuminate\Support\Facades\Artisan::call('sso:clean-tickets', ['--hours' => 24]);
        $this->assertEquals(0, $exitCode);

        // Active ticket must still exist
        $this->assertDatabaseHas('sso_tickets', ['id' => $activeTicket->id]);

        // Recently consumed ticket must still exist
        $this->assertDatabaseHas('sso_tickets', ['id' => $recentConsumedTicket->id]);

        // Expired ticket must be gone
        $this->assertDatabaseMissing('sso_tickets', ['id' => $expiredTicket->id]);

        // Old consumed ticket must be gone
        $this->assertDatabaseMissing('sso_tickets', ['id' => $oldConsumedTicket->id]);
    }

    public function test_sso_issued_token_carries_pms_access_ability_label(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        $exchangeRes = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);
        $exchangeRes->assertStatus(200);

        // Find the most recently issued token in personal_access_tokens
        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $this->eligibleUser->id)
            ->where('name', 'pms_sso_token')
            ->latest('id')
            ->first();

        $this->assertNotNull($tokenRecord);
        $this->assertEquals(['pms:access'], $tokenRecord->abilities);
    }

    public function test_revoking_pms_application_access_immediately_blocks_existing_bearer_token(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        $exchangeRes = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);
        $exchangeRes->assertStatus(200);
        $pmsBearerToken = $exchangeRes->json('token');

        // Access PMS dashboard with Bearer token
        $validReq = $this->withHeader('Authorization', "Bearer {$pmsBearerToken}")
            ->getJson('/api/v1/pm/dashboard');
        $validReq->assertStatus(200);

        // Revoke PMS entitlement in application_access table
        $this->appAccessService->revoke($this->eligibleUser, 'projects', $this->eligibleUser);

        // Access PMS dashboard with SAME Bearer token is immediately blocked
        $blockedReq = $this->withHeader('Authorization', "Bearer {$pmsBearerToken}")
            ->getJson('/api/v1/pm/dashboard');
        $blockedReq->assertStatus(403);
        $blockedReq->assertJsonFragment([
            'message' => 'Forbidden: You do not have access to this application.',
        ]);
    }

    public function test_pms_token_holder_cannot_access_unentitled_applications(): void
    {
        Sanctum::actingAs($this->eligibleUser);
        $ticketRes = $this->postJson('/api/v1/sso/tickets', ['service' => 'pms']);
        $plainTicket = $ticketRes->json('ticket');

        $exchangeRes = $this->postJson('/api/v1/sso/exchange', [
            'ticket' => $plainTicket,
            'service' => 'pms',
        ]);
        $exchangeRes->assertStatus(200);
        $pmsBearerToken = $exchangeRes->json('token');

        // Attempting to access store-admin routes (requires app.access:store)
        $storeReq = $this->withHeader('Authorization', "Bearer {$pmsBearerToken}")
            ->getJson('/api/v1/store-admin/products');
        $storeReq->assertStatus(403);

        // Attempting to access donations admin routes (requires app.access:donations)
        $donationsReq = $this->withHeader('Authorization', "Bearer {$pmsBearerToken}")
            ->getJson('/api/v1/donations/admin/dashboard');
        $donationsReq->assertStatus(403);

        // Attempting to access connect admin routes (requires app.access:connect)
        $connectReq = $this->withHeader('Authorization', "Bearer {$pmsBearerToken}")
            ->getJson('/api/v1/admin/connect');
        $connectReq->assertStatus(403);
    }
}
