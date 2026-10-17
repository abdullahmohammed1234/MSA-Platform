<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DirectMessage;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DirectMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected User $memberA;
    protected User $memberB;
    protected User $adminA;
    protected User $adminB;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'uuid' => (string) Str::uuid(),
            ]
        );

        $this->memberA = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->memberB = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->adminA = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->adminA->roles()->sync([$this->adminRole->id]);

        $this->adminB = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->adminB->roles()->sync([$this->adminRole->id]);
    }

    /**
     * Test 1: Member can initiate a conversation with an authorized administrator.
     */
    public function test_member_can_create_conversation_with_authorized_admin(): void
    {
        $response = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Assalamu alaikum admin, I have a question regarding campus prayer spaces.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('other_participant.id', $this->adminA->id)
            ->assertJsonPath('other_participant.is_admin', true);

        $this->assertDatabaseHas('conversations', [
            'user_one_id' => min($this->memberA->id, $this->adminA->id),
            'user_two_id' => max($this->memberA->id, $this->adminA->id),
        ]);

        $this->assertDatabaseHas('direct_messages', [
            'sender_id' => $this->memberA->id,
            'body' => 'Assalamu alaikum admin, I have a question regarding campus prayer spaces.',
        ]);
    }

    /**
     * Test 2: Administrator can initiate a conversation with an eligible member.
     */
    public function test_admin_can_create_conversation_with_member(): void
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->memberA->id,
                'message' => 'Hello member, reaching out from MSA administration.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('other_participant.id', $this->memberA->id)
            ->assertJsonPath('other_participant.is_admin', false);
    }

    /**
     * Test 3: Administrator can initiate a conversation with another authorized administrator.
     */
    public function test_admin_can_create_conversation_with_another_admin(): void
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminB->id,
                'message' => 'Salam fellow admin, quick sync regarding Friday jummah.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('other_participant.id', $this->adminB->id)
            ->assertJsonPath('other_participant.is_admin', true);
    }

    /**
     * Test 4: Member-to-Member conversation creation is strictly prohibited with 403.
     */
    public function test_member_to_member_conversation_is_strictly_rejected(): void
    {
        $response = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->memberB->id,
                'message' => 'Salam other member.',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Direct messaging is strictly reserved for conversations with an authorized MSA administrator. Member-to-member conversations are prohibited.',
            ]);

        $this->assertDatabaseMissing('conversations', [
            'user_one_id' => min($this->memberA->id, $this->memberB->id),
            'user_two_id' => max($this->memberA->id, $this->memberB->id),
        ]);
    }

    /**
     * Test 5: Self-conversation is rejected with 422.
     */
    public function test_user_cannot_create_conversation_with_themselves(): void
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot start a conversation with yourself.',
            ]);
    }

    /**
     * Test 6: Ineligible or deactivated user cannot initiate or participate.
     */
    public function test_conversation_with_inactive_account_is_rejected(): void
    {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $inactiveUser->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'One or both selected accounts are deactivated or ineligible.',
            ]);
    }

    /**
     * Test 7: Repeated creation for the same pair returns the existing conversation idempotently.
     */
    public function test_repeated_creation_returns_existing_conversation(): void
    {
        // First creation
        $res1 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
            ]);
        $res1->assertStatus(201);
        $convUuid = $res1->json('uuid');

        // Second creation from other participant
        $res2 = $this->actingAs($this->adminA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->memberA->id,
            ]);
        $res2->assertStatus(201)
            ->assertJsonPath('uuid', $convUuid);

        $this->assertEquals(1, Conversation::count());
    }

    /**
     * Test 8: Member can have conversations with multiple distinct administrators.
     */
    public function test_member_can_have_conversations_with_multiple_admins(): void
    {
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id])
            ->assertStatus(201);

        $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminB->id])
            ->assertStatus(201);

        $this->assertEquals(2, Conversation::count());
    }

    /**
     * Test 9: Non-participant user cannot read another conversation (IDOR protection).
     */
    public function test_non_participant_cannot_read_conversation(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id]);
        $convUuid = $res->json('uuid');

        // Member B is not a participant
        $this->actingAs($this->memberB, 'sanctum')
            ->getJson("/api/v1/conversations/{$convUuid}")
            ->assertStatus(403);

        $this->actingAs($this->memberB, 'sanctum')
            ->getJson("/api/v1/conversations/{$convUuid}/messages")
            ->assertStatus(403);
    }

    /**
     * Test 10: Non-participant administrator cannot read another private conversation (Admin-to-Admin privacy).
     */
    public function test_non_participant_admin_cannot_read_another_conversation(): void
    {
        // Conversation between Member A and Admin A
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id]);
        $convUuid = $res->json('uuid');

        // Admin B is an administrator, but NOT a participant in this conversation
        $this->actingAs($this->adminB, 'sanctum')
            ->getJson("/api/v1/conversations/{$convUuid}")
            ->assertStatus(403);
    }

    /**
     * Test 11: Non-participant cannot send messages to another conversation.
     */
    public function test_non_participant_cannot_send_messages(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id]);
        $convUuid = $res->json('uuid');

        $this->actingAs($this->memberB, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'I am an intruder trying to send a message.',
            ])
            ->assertStatus(403);
    }

    /**
     * Test 12: Blank or whitespace-only messages are rejected with 422.
     */
    public function test_blank_or_whitespace_message_is_rejected(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id]);
        $convUuid = $res->json('uuid');

        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", ['body' => '   '])
            ->assertStatus(422);

        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", ['body' => ''])
            ->assertStatus(422);
    }

    /**
     * Test 13: Oversized messages (>5000 characters) are rejected with 422.
     */
    public function test_oversized_message_is_rejected(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id]);
        $convUuid = $res->json('uuid');

        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => str_repeat('a', 5001),
            ])
            ->assertStatus(422);
    }

    /**
     * Test 14: Sender identity is always derived from authenticated user, ignoring forged payload.
     */
    public function test_sender_identity_is_derived_from_authenticated_user(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', ['recipient_id' => $this->adminA->id]);
        $convUuid = $res->json('uuid');

        $msgRes = $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Legitimate text.',
                'sender_id' => $this->adminA->id, // Forged sender attempt
            ]);

        $msgRes->assertStatus(201)
            ->assertJsonPath('sender_id', $this->memberA->id);

        $this->assertDatabaseHas('direct_messages', [
            'sender_id' => $this->memberA->id,
            'body' => 'Legitimate text.',
        ]);
    }

    /**
     * Test 15: Read state updates only authenticated participant.
     */
    public function test_mark_as_read_updates_only_authenticated_participant(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Hello Admin',
            ]);
        $convUuid = $res->json('uuid');
        $conv = Conversation::where('uuid', $convUuid)->first();

        // Admin has not read it yet
        $this->assertEquals(1, $conv->unreadCountFor($this->adminA));

        // Admin marks as read
        $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/read")
            ->assertStatus(200);

        $this->assertEquals(0, $conv->unreadCountFor($this->adminA));
    }

    /**
     * Test 16: Unread count endpoint returns total unread for user across conversations.
     */
    public function test_unread_count_endpoint(): void
    {
        // Member A starts conv with Admin A with a message
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message 1',
            ]);

        // Member B is not involved, but Admin B starts conv with Admin A
        $this->actingAs($this->adminB, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message 2',
            ]);

        // Admin A should have 2 unread messages
        $res = $this->actingAs($this->adminA, 'sanctum')
            ->getJson('/api/v1/conversations/unread-count');

        $res->assertStatus(200)
            ->assertJsonPath('unread_count', 2);
    }

    /**
     * Test 17: In-app notification persists synchronously in database without queue worker.
     */
    public function test_in_app_notification_persists_synchronously(): void
    {
        Queue::fake();

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Notification test message',
            ]);
        $res->assertStatus(201);

        // Queue should NOT have received a job for in-app notification because ImmediateNotification is synchronous
        Queue::assertNothingPushed();

        // Database notification must exist immediately for recipient
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminA->id,
            'type' => DirectMessageNotification::class,
        ]);

        // Sender must NOT receive a notification
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->memberA->id,
            'type' => DirectMessageNotification::class,
        ]);

        // Payload must NOT contain the raw message body
        $notification = Notification::where('user_id', $this->adminA->id)
            ->where('type', DirectMessageNotification::class)
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('Notification test message', json_encode($notification->data));
    }

    /**
     * Test 18: Available admins endpoint returns only active administrators with safe fields.
     */
    public function test_available_admins_endpoint(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->getJson('/api/v1/conversations/recipients/admins');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'uuid', 'name', 'role_label', 'avatar'],
                ],
            ]);

        $ids = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->adminA->id, $ids);
        $this->assertContains($this->adminB->id, $ids);
        $this->assertNotContains($this->memberA->id, $ids);
        $this->assertNotContains($this->memberB->id, $ids);
    }

    /**
     * Test 19: Admin recipients search endpoint allows administrators to find members.
     */
    public function test_admin_recipients_search_endpoint(): void
    {
        $res = $this->actingAs($this->adminA, 'sanctum')
            ->getJson('/api/v1/admin/messaging/recipients?type=members');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'uuid', 'name', 'email', 'avatar', 'role_label', 'is_admin'],
                ],
            ]);

        $ids = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->memberA->id, $ids);
        $this->assertContains($this->memberB->id, $ids);
        $this->assertNotContains($this->adminA->id, $ids);
    }

    /**
     * Test 20: Admin recipients search endpoint returns 403 for ordinary members.
     */
    public function test_admin_recipients_search_fails_for_member(): void
    {
        $this->actingAs($this->memberA, 'sanctum')
            ->getJson('/api/v1/admin/messaging/recipients?type=members')
            ->assertStatus(403);
    }

    /**
     * Test 21: In-app notification is persisted when enabled, regardless of email suppression.
     */
    public function test_in_app_notification_is_persisted_when_email_is_suppressed(): void
    {
        Mail::fake();

        // 1. Initial message - claims email and sends in-app
        $res1 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'First message',
            ]);
        $res1->assertStatus(201);
        $convUuid = $res1->json('uuid');

        $this->assertEquals(1, Notification::where('user_id', $this->adminA->id)->count());
        $participant = ConversationParticipant::where('user_id', $this->adminA->id)->first();
        $this->assertNotNull($participant->last_email_notified_at);
        $initialEmailAt = $participant->last_email_notified_at;

        // 2. Second message inside 5-minute cooldown
        $res2 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Second message inside cooldown window',
            ]);
        $res2->assertStatus(201);

        // Verification: In-app notification for second message IS persisted
        $this->assertEquals(2, Notification::where('user_id', $this->adminA->id)->count());

        // Verification: last_email_notified_at was NOT updated (email was suppressed)
        $participant->refresh();
        $this->assertEquals($initialEmailAt->timestamp, $participant->last_email_notified_at->timestamp);
    }

    /**
     * Test 22: Email is not sent when recipient disables direct-message email notifications.
     */
    public function test_email_not_sent_when_recipient_disables_email_notifications(): void
    {
        Mail::fake();

        NotificationPreference::updateOrCreate(
            ['user_id' => $this->adminA->id],
            [
                'email_enabled' => false,
                'in_app_enabled' => true,
                'direct_messages' => true,
            ]
        );

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message to admin with email disabled',
            ]);
        $res->assertStatus(201);

        // In-app notification persisted
        $this->assertEquals(1, Notification::where('user_id', $this->adminA->id)->count());

        // Email was not claimed or sent
        $participant = ConversationParticipant::where('user_id', $this->adminA->id)->first();
        $this->assertNull($participant->last_email_notified_at);
    }

    /**
     * Test 23: In-app notification is not persisted when recipient disables in-app notifications.
     */
    public function test_in_app_notification_not_persisted_when_disabled(): void
    {
        Mail::fake();

        NotificationPreference::updateOrCreate(
            ['user_id' => $this->adminA->id],
            [
                'email_enabled' => true,
                'in_app_enabled' => false,
                'direct_messages' => true,
            ]
        );

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message to admin with in-app disabled',
            ]);
        $res->assertStatus(201);

        // In-app notification must NOT be created
        $this->assertEquals(0, Notification::where('user_id', $this->adminA->id)->count());

        // Email claim was still made
        $participant = ConversationParticipant::where('user_id', $this->adminA->id)->first();
        $this->assertNotNull($participant->last_email_notified_at);
    }

    /**
     * Test 24: Message to a recently read conversation does not trigger an email.
     */
    public function test_message_to_recently_read_conversation_suppresses_email(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Initial setup message',
            ]);
        $convUuid = $res->json('uuid');
        $conv = Conversation::where('uuid', $convUuid)->first();

        // Admin marks as read 1 minute ago (inside 5-minute recency window)
        $participant = $conv->participantFor($this->adminA);
        $participant->update([
            'last_read_at' => now()->subMinutes(1),
            'last_email_notified_at' => null, // Reset cooldown so only recency window is evaluated
        ]);

        // Member sends new message
        $res2 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Are you still looking at this?',
            ]);
        $res2->assertStatus(201);

        // In-app notification persisted
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminA->id,
        ]);

        // Email was suppressed due to recent reading (< 5 min)
        $participant->refresh();
        $this->assertNull($participant->last_email_notified_at);
    }

    /**
     * Test 25: First eligible message outside recent-read window can trigger an email.
     */
    public function test_first_message_outside_recent_read_window_triggers_email(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Initial message',
            ]);
        $convUuid = $res->json('uuid');
        $conv = Conversation::where('uuid', $convUuid)->first();

        // Admin last read 10 minutes ago (outside the 5-minute recency window)
        // and last email was 10 minutes ago (outside the 5-minute cooldown)
        $participant = $conv->participantFor($this->adminA);
        $participant->update([
            'last_read_at' => now()->subMinutes(10),
            'last_email_notified_at' => now()->subMinutes(10),
        ]);

        // Member sends new message
        $res2 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Message sent 10 minutes later',
            ]);
        $res2->assertStatus(201);

        // Verification: email was successfully claimed
        $participant->refresh();
        $this->assertTrue($participant->last_email_notified_at->greaterThan(now()->subMinutes(1)));
    }

    /**
     * Test 26: Subsequent messages in same conversation and cooldown window do not trigger additional emails.
     */
    public function test_subsequent_messages_in_cooldown_do_not_trigger_additional_emails(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Msg 1',
            ]);
        $convUuid = $res->json('uuid');
        $conv = Conversation::where('uuid', $convUuid)->first();
        $participant = $conv->participantFor($this->adminA);
        $firstEmailAt = $participant->last_email_notified_at;
        $this->assertNotNull($firstEmailAt);

        // Msg 2 sent inside cooldown
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", ['body' => 'Msg 2'])
            ->assertStatus(201);

        // Msg 3 sent inside cooldown
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", ['body' => 'Msg 3'])
            ->assertStatus(201);

        // All 3 in-app notifications exist
        $this->assertEquals(3, Notification::where('user_id', $this->adminA->id)->count());

        // Cooldown timestamp remained unchanged from Msg 1
        $participant->refresh();
        $this->assertEquals($firstEmailAt->timestamp, $participant->last_email_notified_at->timestamp);
    }

    /**
     * Test 27: Messages in different conversations have independent cooldowns.
     */
    public function test_messages_in_different_conversations_have_independent_cooldowns(): void
    {
        Mail::fake();

        // Conv 1: Member A -> Admin A
        $res1 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message from Member A',
            ]);
        $res1->assertStatus(201);
        $conv1 = Conversation::where('uuid', $res1->json('uuid'))->first();

        // Conv 2: Member B -> Admin A
        $res2 = $this->actingAs($this->memberB, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message from Member B',
            ]);
        $res2->assertStatus(201);
        $conv2 = Conversation::where('uuid', $res2->json('uuid'))->first();

        // Both conversations should have claimed email for Admin A independently
        $part1 = $conv1->participantFor($this->adminA);
        $part2 = $conv2->participantFor($this->adminA);

        $this->assertNotNull($part1->last_email_notified_at);
        $this->assertNotNull($part2->last_email_notified_at);
        $this->assertEquals(2, Notification::where('user_id', $this->adminA->id)->count());
    }

    /**
     * Test 28: Email can be sent again after cooldown expires (> 5 minutes).
     */
    public function test_email_can_be_sent_again_after_cooldown_expires(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message 1',
            ]);
        $convUuid = $res->json('uuid');
        $conv = Conversation::where('uuid', $convUuid)->first();
        $participant = $conv->participantFor($this->adminA);

        // Manually simulate that 6 minutes have passed since the last email and last read
        $participant->update([
            'last_email_notified_at' => now()->subMinutes(6),
            'last_read_at' => now()->subMinutes(15),
        ]);

        // New message sent after cooldown expiry
        $res2 = $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Message after cooldown expired',
            ]);
        $res2->assertStatus(201);

        $participant->refresh();
        $this->assertTrue($participant->last_email_notified_at->greaterThan(now()->subMinutes(1)));
    }

    /**
     * Test 29: Concurrent sends cannot produce duplicate cooldown claims or bypass throttling.
     */
    public function test_concurrency_safe_email_claim(): void
    {
        $conv = Conversation::create([
            'user_one_id' => min($this->memberA->id, $this->adminA->id),
            'user_two_id' => max($this->memberA->id, $this->adminA->id),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->memberA->id,
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminA->id,
        ]);

        // First claim attempt succeeds
        $claim1 = DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA);
        $this->assertTrue($claim1);

        // Immediate second claim attempt fails (atomically protected by DB update)
        $claim2 = DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA);
        $this->assertFalse($claim2);
    }

    /**
     * Test 30: Email transport failure does not roll back persisted message or in-app notification.
     */
    public function test_email_transport_failure_does_not_rollback_persisted_message(): void
    {
        // Bind a throwing MailChannel to simulate transport failure
        $this->app->bind(\Illuminate\Notifications\Channels\MailChannel::class, function () {
            $mock = \Mockery::mock(\Illuminate\Notifications\Channels\MailChannel::class);
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('Simulated SMTP connection failure'));
            return $mock;
        });

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message during SMTP outage',
            ]);

        // Message persistence and HTTP 201 must still succeed
        $res->assertStatus(201);
        $this->assertDatabaseHas('direct_messages', [
            'body' => 'Message during SMTP outage',
        ]);

        // In-app notification must remain persisted
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminA->id,
            'type' => DirectMessageNotification::class,
        ]);
    }

    /**
     * Test 31: Self-notification is prevented.
     */
    public function test_self_notification_is_prevented(): void
    {
        $conv = Conversation::create([
            'user_one_id' => min($this->memberA->id, $this->adminA->id),
            'user_two_id' => max($this->memberA->id, $this->adminA->id),
        ]);

        $notification = new DirectMessageNotification($conv, $this->memberA);

        // Via called with sender as notifiable
        $channels = $notification->via($this->memberA);
        $this->assertEmpty($channels);
    }

    /**
     * Test 32: Direct messages category opt-out suppresses all notification channels.
     */
    public function test_direct_messages_category_opt_out_suppresses_notifications(): void
    {
        NotificationPreference::updateOrCreate(
            ['user_id' => $this->adminA->id],
            [
                'direct_messages' => false,
                'email_enabled' => true,
                'in_app_enabled' => true,
            ]
        );

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Category opt-out test',
            ]);
        $res->assertStatus(201);

        $this->assertEquals(0, Notification::where('user_id', $this->adminA->id)->count());
        $participant = ConversationParticipant::where('user_id', $this->adminA->id)->first();
        $this->assertNull($participant->last_email_notified_at);
    }

    /**
     * Test 33: Broad platform or infrastructure permissions do not qualify ordinary user as DM admin.
     */
    public function test_broad_platform_permissions_do_not_qualify_as_dm_admin(): void
    {
        $opsPerm = Permission::firstOrCreate([
            'slug' => 'platform.operations',
        ], [
            'name' => 'Platform Operations',
            'uuid' => (string) Str::uuid(),
            'module' => 'platform',
        ]);

        $sysPerm = Permission::firstOrCreate([
            'slug' => 'system.view',
        ], [
            'name' => 'System View',
            'uuid' => (string) Str::uuid(),
            'module' => 'system',
        ]);

        $opsUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $opsUser->permissions()->sync([$opsPerm->id, $sysPerm->id]);

        // Must NOT qualify as DM admin
        $this->assertFalse(DirectMessagingAuthorizationService::isAdmin($opsUser));

        // Attempting to start conversation between Member A and this user must return 403
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $opsUser->id,
                'message' => 'Attempting to message ops technician',
            ])
            ->assertStatus(403);
    }

    /**
     * Test 34: Read-cursor updates only advance monotonically and do not mark subsequent messages read.
     */
    public function test_read_cursor_monotonically_advances(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Message 1',
            ]);
        $convUuid = $res->json('uuid');
        $conv = Conversation::where('uuid', $convUuid)->first();

        // Admin marks as read
        $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/read")
            ->assertStatus(200);

        $this->assertEquals(0, $conv->fresh()->unreadCountFor($this->adminA));
        $firstReadAt = $conv->participantFor($this->adminA)->last_read_at;

        // Advance clock by 1 second to simulate subsequent arrival
        $this->travel(1)->seconds();

        // Member sends Message 2
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Message 2 arrives after read',
            ]);

        // Unread count must now be 1
        $this->assertEquals(1, $conv->fresh()->unreadCountFor($this->adminA));

        // Passing an older timestamp or message_id does not move cursor backwards
        $this->actingAs($this->adminA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/read", [
                'message_id' => 1, // Stale message
            ])
            ->assertStatus(200);

        // Cursor did not regress
        $currentReadAt = $conv->participantFor($this->adminA)->last_read_at;
        $this->assertTrue($currentReadAt->greaterThanOrEqualTo($firstReadAt));
    }

    /**
     * Test 35: Releasing an email claim does not clear a newer claim owned by another attempt.
     */
    public function test_release_email_claim_does_not_clear_newer_claim(): void
    {
        $conv = Conversation::create([
            'user_one_id' => min($this->memberA->id, $this->adminA->id),
            'user_two_id' => max($this->memberA->id, $this->adminA->id),
        ]);

        $staleClaimTime = now()->subMinutes(10);
        $newerClaimTime = now()->subMinutes(2);

        $part = ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminA->id,
            'last_email_notified_at' => $newerClaimTime,
        ]);

        // Attempting to release with a stale timestamp does not clear the newer claim
        $released = DirectMessagingAuthorizationService::releaseEmailNotificationClaim($conv, $this->adminA, $staleClaimTime);
        $this->assertFalse($released);

        $part->refresh();
        $this->assertEquals($newerClaimTime->timestamp, $part->last_email_notified_at->timestamp);

        // Releasing with matching newer timestamp succeeds
        $released2 = DirectMessagingAuthorizationService::releaseEmailNotificationClaim($conv, $this->adminA, $newerClaimTime);
        $this->assertTrue($released2);

        $part->refresh();
        $this->assertNull($part->last_email_notified_at);
    }

    /**
     * Test 36: Email delivery failure logs correlation ID and error classification without leaking secrets.
     */
    public function test_email_delivery_failure_logging_is_sanitized(): void
    {
        Log::spy();

        // Bind MailChannel to throw an exception with mock sensitive strings
        $this->app->bind(\Illuminate\Notifications\Channels\MailChannel::class, function () {
            $mock = \Mockery::mock(\Illuminate\Notifications\Channels\MailChannel::class);
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('SMTP auth fail: user@secret.com password=supersecret'));
            return $mock;
        });

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Sensitive message content that must not appear in logs',
            ]);
        $res->assertStatus(201);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'DirectMessageNotification delivery failure',
                \Mockery::on(function ($context) {
                    // Check correlation_id exists and is UUID
                    $hasCorrelation = isset($context['correlation_id']) && Str::isUuid($context['correlation_id']);
                    // Check error classification is class name
                    $hasClassification = isset($context['error_classification']) && $context['error_classification'] === 'RuntimeException';
                    // Verify message body, recipient email, and raw error message are NOT present
                    $noErrorMessage = !isset($context['error_message']);
                    $contextString = json_encode($context);
                    $noLeak = !str_contains($contextString, 'supersecret')
                           && !str_contains($contextString, 'Sensitive message content')
                           && !str_contains($contextString, 'user@secret.com');

                    return $hasCorrelation && $hasClassification && $noErrorMessage && $noLeak;
                })
            );
    }

    /**
     * Test 37: Precise timestamp boundary behavior for recent-read suppression and email cooldown.
     */
    public function test_recent_read_and_cooldown_boundary_conditions(): void
    {
        $conv = Conversation::create([
            'user_one_id' => min($this->memberA->id, $this->adminA->id),
            'user_two_id' => max($this->memberA->id, $this->adminA->id),
        ]);

        $part = ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminA->id,
        ]);

        $now = now();

        // 1. last_read_at inside window: 299 seconds ago (< 5m) -> Suppressed
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => $now->copy()->subSeconds(299),
            'last_email_notified_at' => null,
        ]);
        $this->assertFalse(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));

        // 2. last_read_at exactly 300 seconds ago (5m) -> Allowed (<= cutoff)
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => $now->copy()->subSeconds(300),
            'last_email_notified_at' => null,
        ]);
        $this->assertTrue(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));

        // 3. last_read_at outside window: 301 seconds ago (> 5m) -> Allowed (<= cutoff)
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => $now->copy()->subSeconds(301),
            'last_email_notified_at' => null,
        ]);
        $this->assertTrue(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));

        // 4. last_read_at null -> Allowed
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => null,
            'last_email_notified_at' => null,
        ]);
        $this->assertTrue(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));

        // 5. last_email_notified_at inside cooldown: 299 seconds ago (< 5m) -> Suppressed
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => null,
            'last_email_notified_at' => $now->copy()->subSeconds(299),
        ]);
        $this->assertFalse(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));

        // 6. last_email_notified_at exactly 300 seconds ago (5m) -> Allowed (<= cutoff)
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => null,
            'last_email_notified_at' => $now->copy()->subSeconds(300),
        ]);
        $this->assertTrue(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));

        // 7. last_email_notified_at outside cooldown: 301 seconds ago (> 5m) -> Allowed (<= cutoff)
        DB::table('conversation_participants')->where('id', $part->id)->update([
            'last_read_at' => null,
            'last_email_notified_at' => $now->copy()->subSeconds(301),
        ]);
        $this->assertTrue(DirectMessagingAuthorizationService::claimEmailNotification($conv, $this->adminA));
    }

    /**
     * Test 38: Composite unique constraint is enforced on conversation_participants.
     */
    public function test_composite_unique_constraint_enforced_on_conversation_participants(): void
    {
        $conv = Conversation::create([
            'user_one_id' => min($this->memberA->id, $this->adminA->id),
            'user_two_id' => max($this->memberA->id, $this->adminA->id),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminA->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempting to insert duplicate participant row must fail at DB level
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminA->id,
        ]);
    }

    /**
     * Test 39: Account with manage_users permission qualifies as DM administrator.
     */
    public function test_account_with_manage_users_permission_qualifies_as_dm_admin(): void
    {
        $perm = Permission::firstOrCreate([
            'slug' => 'manage_users',
        ], [
            'name' => 'Manage Users',
            'uuid' => (string) Str::uuid(),
            'module' => 'Admin',
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->permissions()->sync([$perm->id]);

        $this->assertTrue(DirectMessagingAuthorizationService::isAdmin($user));

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $user->id,
                'message' => 'Salam user manager',
            ]);
        $res->assertStatus(201);
    }

    /**
     * Test 40: Account with manage_support permission qualifies as DM administrator.
     */
    public function test_account_with_manage_support_permission_qualifies_as_dm_admin(): void
    {
        $perm = Permission::firstOrCreate([
            'slug' => 'manage_support',
        ], [
            'name' => 'Manage Support',
            'uuid' => (string) Str::uuid(),
            'module' => 'Support',
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->permissions()->sync([$perm->id]);

        $this->assertTrue(DirectMessagingAuthorizationService::isAdmin($user));

        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $user->id,
                'message' => 'Salam support agent',
            ]);
        $res->assertStatus(201);
    }

    /**
     * Test 41: Inactive administrator cannot participate in or create conversations.
     */
    public function test_inactive_administrator_cannot_participate_in_conversations(): void
    {
        $inactiveAdmin = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $inactiveAdmin->roles()->sync([$this->adminRole->id]);

        // isAdmin must return false for inactive user
        $this->assertFalse(DirectMessagingAuthorizationService::isAdmin($inactiveAdmin));
        $this->assertFalse(DirectMessagingAuthorizationService::isEligibleParticipant($inactiveAdmin));

        // Member cannot initiate conversation with inactive admin
        $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $inactiveAdmin->id,
                'message' => 'Hello inactive admin',
            ])
            ->assertStatus(422);
    }

    /**
     * Test 42: Super admin who is not a conversation participant is forbidden from viewing or replying.
     */
    public function test_super_admin_cannot_access_or_reply_to_unjoined_conversation(): void
    {
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrator', 'uuid' => (string) Str::uuid()]
        );
        $superAdmin = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $superAdmin->roles()->sync([$superAdminRole->id]);

        // Conversation between Member A and Admin A
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Private conversation',
            ]);
        $res->assertStatus(201);
        $convUuid = $res->json('uuid');

        // Super admin tries to view conversation
        $this->actingAs($superAdmin, 'sanctum')
            ->getJson("/api/v1/conversations/{$convUuid}")
            ->assertStatus(403);

        // Super admin tries to send message
        $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Super admin snooping in',
            ])
            ->assertStatus(403);
    }

    /**
     * Test 43: Ordinary member cannot access administrative recipient directory.
     */
    public function test_member_cannot_access_admin_recipient_directory(): void
    {
        // Member tries to access admin messaging recipient search
        $this->actingAs($this->memberA, 'sanctum')
            ->getJson('/api/v1/admin/messaging/recipients')
            ->assertStatus(403);

        // Member accessing available-admins only gets administrators
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->getJson('/api/v1/conversations/recipients/admins');
        $res->assertStatus(200);

        $returnedIds = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->adminA->id, $returnedIds);
        $this->assertNotContains($this->memberB->id, $returnedIds);
    }

    /**
     * Test 44: Revoking an admin role immediately revokes access to existing conversations.
     */
    public function test_revoking_admin_role_immediately_blocks_conversation_access(): void
    {
        $res = $this->actingAs($this->memberA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $this->adminA->id,
                'message' => 'Conversation while admin is active',
            ]);
        $res->assertStatus(201);
        $convUuid = $res->json('uuid');

        // Revoke admin role from Admin A
        $this->adminA->roles()->detach();
        $this->assertFalse(DirectMessagingAuthorizationService::isAdmin($this->adminA->fresh()));

        // Conversation now has 0 administrators, violating the core invariant
        // Member A can no longer view or reply
        $this->actingAs($this->memberA, 'sanctum')
            ->getJson("/api/v1/conversations/{$convUuid}")
            ->assertStatus(403);

        $this->actingAs($this->memberA, 'sanctum')
            ->postJson("/api/v1/conversations/{$convUuid}/messages", [
                'body' => 'Message after admin revoked',
            ])
            ->assertStatus(403);

        // Demoted user also cannot view or reply
        $this->actingAs($this->adminA, 'sanctum')
            ->getJson("/api/v1/conversations/{$convUuid}")
            ->assertStatus(403);
    }
}
