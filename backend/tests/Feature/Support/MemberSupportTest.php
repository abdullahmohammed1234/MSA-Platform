<?php

namespace Tests\Feature\Support;

use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportInquiry;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportReplyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberSupportTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;
    protected User $otherMember;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard member
        $this->member = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create second member for IDOR tests
        $this->otherMember = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create staff user with Admin role and manage_support permission
        $this->staffUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'uuid' => (string) Str::uuid(),
            ]
        );

        $supportPerm = Permission::firstOrCreate(
            ['slug' => 'manage_support'],
            [
                'name' => 'Manage Support',
                'module' => 'Support',
                'uuid' => (string) Str::uuid(),
            ]
        );

        $adminRole->permissions()->syncWithoutDetaching([$supportPerm->id]);
        $this->staffUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    public function test_categories_endpoint_returns_allowed_categories(): void
    {
        $response = $this->getJson('/api/v1/support/categories');

        $response->assertOk()
            ->assertJsonStructure(['categories' => [['key', 'name']]]);

        $keys = collect($response->json('categories'))->pluck('key')->all();
        $this->assertContains('general', $keys);
        $this->assertContains('new_muslim', $keys);
        $this->assertContains('faith_questions', $keys);
    }

    public function test_resources_status_is_disabled_by_default(): void
    {
        $response = $this->getJson('/api/v1/support/resources/status');

        $response->assertOk()
            ->assertJson(['enabled' => false]);
    }

    public function test_unauthenticated_user_cannot_access_inquiries(): void
    {
        $this->getJson('/api/v1/support/inquiries')->assertStatus(401);
        $this->postJson('/api/v1/support/inquiries', [])->assertStatus(401);
    }

    public function test_member_can_submit_valid_support_inquiry(): void
    {
        $payload = [
            'category' => 'new_muslim',
            'subject' => 'Question regarding prayer spaces at SFU Burnaby',
            'message' => 'Assalamu alaikum, I am new to campus and would like guidance on finding AQ 3200.'
        ];

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/support/inquiries', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('inquiry.category', 'new_muslim')
            ->assertJsonPath('inquiry.subject', 'Question regarding prayer spaces at SFU Burnaby')
            ->assertJsonPath('inquiry.status', 'open');

        $inquiryUuid = $response->json('inquiry.uuid');
        $this->assertDatabaseHas('support_inquiries', [
            'uuid' => $inquiryUuid,
            'user_id' => $this->member->id,
            'category' => 'new_muslim',
        ]);

        $this->assertDatabaseHas('support_messages', [
            'user_id' => $this->member->id,
            'is_staff_reply' => false,
            'message' => 'Assalamu alaikum, I am new to campus and would like guidance on finding AQ 3200.',
        ]);
    }

    public function test_inquiry_submission_validates_required_fields_and_category(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/support/inquiries', [
                'category' => 'invalid_category_xyz',
                'subject' => '',
                'message' => 'hi',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category', 'subject', 'message']);
    }

    public function test_member_can_list_their_own_inquiries_only(): void
    {
        // Member 1 inquiry
        $inquiry1 = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Member 1 Inquiry',
            'status' => 'open',
        ]);

        // Member 2 inquiry
        $inquiry2 = SupportInquiry::create([
            'user_id' => $this->otherMember->id,
            'category' => 'general',
            'subject' => 'Member 2 Inquiry',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/support/inquiries');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $inquiry1->uuid);
    }

    public function test_member_cannot_view_or_reply_to_another_members_inquiry_idor(): void
    {
        $otherInquiry = SupportInquiry::create([
            'user_id' => $this->otherMember->id,
            'category' => 'faith_questions',
            'subject' => 'Private question',
            'status' => 'open',
        ]);

        // Attempt show
        $this->actingAs($this->member, 'sanctum')
            ->getJson("/api/v1/support/inquiries/{$otherInquiry->uuid}")
            ->assertStatus(403);

        // Attempt reply
        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$otherInquiry->uuid}/messages", [
                'message' => 'Sneaky reply',
            ])
            ->assertStatus(403);

        // Attempt close
        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$otherInquiry->uuid}/close")
            ->assertStatus(403);
    }

    public function test_member_can_send_follow_up_message_to_open_inquiry(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'campus_prayer',
            'subject' => 'Prayer room keycard',
            'status' => 'awaiting_member',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$inquiry->uuid}/messages", [
                'message' => 'Here is my student number and card details.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('support_messages', [
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->member->id,
            'is_staff_reply' => false,
            'message' => 'Here is my student number and card details.',
        ]);

        // Inquiry transitions to awaiting_staff
        $this->assertEquals('awaiting_staff', $inquiry->fresh()->status);
    }

    public function test_member_cannot_send_message_to_closed_inquiry(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'campus_prayer',
            'subject' => 'Resolved issue',
            'status' => 'closed',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$inquiry->uuid}/messages", [
                'message' => 'Trying to message closed thread.',
            ]);

        $response->assertStatus(403);
    }

    public function test_member_can_close_their_inquiry(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'campus_prayer',
            'subject' => 'Issue sorted',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$inquiry->uuid}/close");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('inquiry.status', 'closed');

        $this->assertNotNull($inquiry->fresh()->closed_at);
    }

    public function test_staff_can_view_triage_list_and_stats(): void
    {
        SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'General Inquiry 1',
            'status' => 'open',
        ]);

        SupportInquiry::create([
            'user_id' => $this->otherMember->id,
            'category' => 'faith_questions',
            'subject' => 'Faith Inquiry 2',
            'status' => 'in_review',
        ]);

        // Staff lists all
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/staff/support/inquiries');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Staff checks stats
        $statsResponse = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/v1/staff/support/stats');

        $statsResponse->assertOk()
            ->assertJsonPath('total_open', 1)
            ->assertJsonPath('in_review', 1)
            ->assertJsonPath('unassigned', 2);
    }

    public function test_non_staff_member_is_forbidden_from_staff_endpoints(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/staff/support/inquiries')
            ->assertStatus(403);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/staff/support/stats')
            ->assertStatus(403);
    }

    public function test_staff_can_reply_and_dispatch_notification(): void
    {
        LaravelNotification::fake();

        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'new_muslim',
            'subject' => 'Welcoming inquiry',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson("/api/v1/staff/support/inquiries/{$inquiry->uuid}/messages", [
                'message' => 'Wa alaikum assalam! We would be honored to meet you at AQ 3200.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify message saved with is_staff_reply = true
        $this->assertDatabaseHas('support_messages', [
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->staffUser->id,
            'is_staff_reply' => true,
            'message' => 'Wa alaikum assalam! We would be honored to meet you at AQ 3200.',
        ]);

        // Verify status moved to awaiting_member and assigned to staff
        $fresh = $inquiry->fresh();
        $this->assertEquals('awaiting_member', $fresh->status);
        $this->assertEquals($this->staffUser->id, $fresh->assigned_to_user_id);

        // Verify notification dispatched to member
        LaravelNotification::assertSentTo($this->member, SupportReplyNotification::class);
    }

    public function test_staff_can_update_status_and_assignment(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Status check',
            'status' => 'open',
        ]);

        // Status update
        $this->actingAs($this->staffUser, 'sanctum')
            ->patchJson("/api/v1/staff/support/inquiries/{$inquiry->uuid}/status", [
                'status' => 'in_review',
            ])
            ->assertOk()
            ->assertJsonPath('inquiry.status', 'in_review');

        // Assignment update
        $this->actingAs($this->staffUser, 'sanctum')
            ->patchJson("/api/v1/staff/support/inquiries/{$inquiry->uuid}/assign", [
                'assigned_to_user_id' => $this->staffUser->id,
            ])
            ->assertOk()
            ->assertJsonPath('inquiry.assigned_to_user_id', $this->staffUser->id);
    }

    public function test_inquiry_can_be_accessed_and_replied_via_reference_code(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Reference Code Test',
            'status' => 'open',
        ]);

        $this->assertNotEmpty($inquiry->reference_code);

        // Member view via reference code
        $showResponse = $this->actingAs($this->member, 'sanctum')
            ->getJson("/api/v1/support/inquiries/{$inquiry->reference_code}");

        $showResponse->assertOk()
            ->assertJsonPath('inquiry.reference_code', $inquiry->reference_code);

        // Member reply via reference code
        $replyResponse = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => 'Follow up using human reference code',
            ]);

        $replyResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('inquiry.reference_code', $inquiry->reference_code)
            ->assertJsonPath('data.body', 'Follow up using human reference code');

        // Staff view via reference code
        $staffShowResponse = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}");

        $staffShowResponse->assertOk()
            ->assertJsonPath('inquiry.reference_code', $inquiry->reference_code);

        // Staff reply via reference code
        $staffReplyResponse = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => 'Staff response using reference code',
            ]);

        $staffReplyResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('inquiry.reference_code', $inquiry->reference_code)
            ->assertJsonPath('data.body', 'Staff response using reference code');
    }

    public function test_inquiry_submission_rejects_empty_stripped_subject_and_message(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/support/inquiries', [
                'category' => 'general',
                'subject' => '<script></script>   ',
                'message' => '<b>   </b>',
            ]);

        $response->assertStatus(422);
    }

    public function test_member_reply_to_resolved_inquiry_reopens_to_open(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'campus_prayer',
            'subject' => 'Need help with prayer schedule',
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => 'Actually, I had one more follow up question.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('inquiry.status', 'open');

        $this->assertEquals('open', $inquiry->fresh()->status);
    }

    public function test_staff_can_update_status_to_in_progress(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Investigating issue',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->patchJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertOk()
            ->assertJsonPath('inquiry.status', 'in_progress');

        $this->assertEquals('in_progress', $inquiry->fresh()->status);
    }

    public function test_support_reply_notification_contains_action_url_and_action_label(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Notification Payload Test',
            'status' => 'open',
        ]);

        $message = SupportMessage::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->staffUser->id,
            'is_staff_reply' => true,
            'message' => 'Staff response text.',
        ]);

        $notification = new SupportReplyNotification($inquiry, $message);
        $arrayData = $notification->toArray($this->member);

        $this->assertArrayHasKey('action_url', $arrayData);
        $this->assertEquals('/app/support/' . $inquiry->reference_code, $arrayData['action_url']);
        $this->assertArrayHasKey('action_label', $arrayData);
        $this->assertEquals('View Conversation', $arrayData['action_label']);
    }
}
