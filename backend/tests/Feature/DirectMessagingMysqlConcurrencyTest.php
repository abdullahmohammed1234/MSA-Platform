<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DirectMessage;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DirectMessagingMysqlConcurrencyTest extends TestCase
{
    protected array $createdUserIds = [];
    protected array $createdConversationIds = [];
    protected array $createdRoleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Enforce safety guard: This test suite MUST run against MySQL and explicitly target a test database
        $connection = config('database.default');
        $dbName = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'mysql') {
            $this->markTestSkipped("DirectMessagingMysqlConcurrencyTest must be run with DB_CONNECTION=mysql. Current: {$connection}");
        }

        if (!str_ends_with($dbName, '_test')) {
            $this->fail("SAFETY GUARD ABORT: Test target database '{$dbName}' must end in '_test'.");
        }
    }

    protected function tearDown(): void
    {
        // Safe, guarded cleanup of only fixtures created by this test run
        if (!empty($this->createdConversationIds)) {
            DB::table('direct_messages')->whereIn('conversation_id', $this->createdConversationIds)->delete();
            DB::table('conversation_participants')->whereIn('conversation_id', $this->createdConversationIds)->delete();
            DB::table('conversations')->whereIn('id', $this->createdConversationIds)->delete();
        }

        if (!empty($this->createdUserIds)) {
            DB::table('notifications')->whereIn('user_id', $this->createdUserIds)->delete();
            DB::table('notification_preferences')->whereIn('user_id', $this->createdUserIds)->delete();
            DB::table('role_user')->whereIn('user_id', $this->createdUserIds)->delete();
            DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
        }

        if (!empty($this->createdRoleIds)) {
            DB::table('role_user')->whereIn('role_id', $this->createdRoleIds)->delete();
            DB::table('roles')->whereIn('id', $this->createdRoleIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * Create an isolated user fixture tracked for safe teardown.
     */
    protected function createTrackedUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));

        $this->createdUserIds[] = $user->id;
        return $user;
    }

    /**
     * Create an isolated conversation fixture tracked for safe teardown.
     */
    protected function createTrackedConversation(User $userA, User $userB): Conversation
    {
        $conv = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'user_one_id' => min($userA->id, $userB->id),
            'user_two_id' => max($userA->id, $userB->id),
        ]);

        $this->createdConversationIds[] = $conv->id;
        return $conv;
    }

    /**
     * Open an independent PDO connection to the MySQL test database.
     */
    protected function openIndependentPdo(): PDO
    {
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    /**
     * Worker processes must target the same isolated test database as this process.
     *
     * @return array<string, string>
     */
    protected function workerEnv(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_URL' => '',
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_HOST' => (string) config('database.connections.mysql.host', '127.0.0.1'),
            'DB_PORT' => (string) config('database.connections.mysql.port', '3306'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];
    }

    /**
     * Test 1: Verify MySQL version, InnoDB storage engines, composite indexes, and schema constraints.
     */
    public function test_mysql_innodb_environment_verification(): void
    {
        $pdo = DB::connection('mysql')->getPdo();
        $this->assertEquals('mysql', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $this->assertNotEmpty($serverVersion);

        // Verify all core messaging tables use InnoDB
        $tables = ['conversations', 'conversation_participants', 'direct_messages'];
        $stmt = $pdo->query(
            "SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('conversations', 'conversation_participants', 'direct_messages')"
        );
        $engineMap = [];
        while ($row = $stmt->fetch()) {
            $engineMap[$row['TABLE_NAME']] = strtolower($row['ENGINE']);
        }

        foreach ($tables as $table) {
            $this->assertArrayHasKey($table, $engineMap);
            $this->assertEquals('innodb', $engineMap[$table], "Table {$table} must use InnoDB engine for transaction and row-locking safety.");
        }

        // Verify composite unique index on conversations (user_one_id, user_two_id)
        $stmt = $pdo->query("SHOW INDEX FROM conversations WHERE Key_name = 'conversations_participants_pair_unique'");
        $convPairCols = [];
        while ($row = $stmt->fetch()) {
            $convPairCols[] = $row['Column_name'];
            $this->assertEquals(0, $row['Non_unique']);
        }
        $this->assertEquals(['user_one_id', 'user_two_id'], $convPairCols);

        // Verify composite unique index on conversation_participants (conversation_id, user_id)
        $stmt = $pdo->query("SHOW INDEX FROM conversation_participants WHERE Key_name = 'conv_part_unique'");
        $partPairCols = [];
        while ($row = $stmt->fetch()) {
            $partPairCols[] = $row['Column_name'];
            $this->assertEquals(0, $row['Non_unique']);
        }
        $this->assertEquals(['conversation_id', 'user_id'], $partPairCols);

        // Verify last_email_notified_at column is nullable timestamp
        $stmt = $pdo->query(
            "SELECT IS_NULLABLE, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversation_participants' AND COLUMN_NAME = 'last_email_notified_at'"
        );
        $col = $stmt->fetch();
        $this->assertNotEmpty($col);
        $this->assertEquals('YES', $col['IS_NULLABLE']);
        $this->assertEquals('timestamp', $col['DATA_TYPE']);
    }

    /**
     * Test 2: Two independent database connections competing for the same email cooldown claim.
     * Demonstrates atomic conditional update and row-level serialization in MySQL InnoDB.
     */
    public function test_independent_connections_atomic_email_claim(): void
    {
        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();
        $conv = $this->createTrackedConversation($member, $admin);

        // Create participant row with no prior reads and no prior email
        DB::table('conversation_participants')->insert([
            'conversation_id' => $conv->id,
            'user_id' => $admin->id,
            'last_read_at' => null,
            'last_email_notified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdo1 = $this->openIndependentPdo();
        $pdo2 = $this->openIndependentPdo();

        $connId1 = $pdo1->query("SELECT CONNECTION_ID()")->fetchColumn();
        $connId2 = $pdo2->query("SELECT CONNECTION_ID()")->fetchColumn();
        $this->assertNotEquals($connId1, $connId2, "Connections must be distinct client sessions on the MySQL server.");

        $sql = "UPDATE conversation_participants
                SET last_email_notified_at = :now1, updated_at = :now2
                WHERE conversation_id = :conv_id
                  AND user_id = :user_id
                  AND (last_read_at IS NULL OR last_read_at <= :recencyCutoff)
                  AND (last_email_notified_at IS NULL OR last_email_notified_at <= :cooldownCutoff)";

        $now = now()->toDateTimeString();
        $cutoff = now()->subMinutes(5)->toDateTimeString();

        $params = [
            'now1' => $now,
            'now2' => $now,
            'conv_id' => $conv->id,
            'user_id' => $admin->id,
            'recencyCutoff' => $cutoff,
            'cooldownCutoff' => $cutoff,
        ];

        // Connection 1 attempts claim
        $stmt1 = $pdo1->prepare($sql);
        $stmt1->execute($params);
        $affected1 = $stmt1->rowCount();

        // Connection 2 attempts claim concurrently for the same window
        $stmt2 = $pdo2->prepare($sql);
        $stmt2->execute($params);
        $affected2 = $stmt2->rowCount();

        // Exactly one connection must have successfully claimed the row
        $this->assertEquals(1, $affected1, "First connection must claim the cooldown window.");
        $this->assertEquals(0, $affected2, "Second connection must be suppressed (0 rows affected).");

        // Verify the database row now has last_email_notified_at set
        $participant = DB::table('conversation_participants')
            ->where('conversation_id', $conv->id)
            ->where('user_id', $admin->id)
            ->first();
        $this->assertNotNull($participant->last_email_notified_at);
    }

    /**
     * Test 3: Multi-process simultaneous race condition test using synchronization barrier.
     * Launches two distinct OS processes competing for the email notification claim on MySQL.
     */
    public function test_multi_process_simultaneous_email_claim_race(): void
    {
        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();
        $conv = $this->createTrackedConversation($member, $admin);

        DB::table('conversation_participants')->insert([
            'conversation_id' => $conv->id,
            'user_id' => $admin->id,
            'last_read_at' => null,
            'last_email_notified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $barrierFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dm_race_' . uniqid() . '.barrier';
        touch($barrierFile);

        $workerScript = base_path('tests/Feature/ConcurrencyWorkers/dm_worker.php');

        // Set up two independent PHP processes targeting MySQL
        $env = $this->workerEnv();

        $proc1 = new Process(['php', $workerScript, 'claim', (string)$conv->id, (string)$admin->id, $barrierFile], null, $env);
        $proc2 = new Process(['php', $workerScript, 'claim', (string)$conv->id, (string)$admin->id, $barrierFile], null, $env);

        $proc1->start();
        $proc2->start();

        // Allow both processes to boot and reach the synchronization barrier
        usleep(300000); // 300ms boot time

        // Release the barrier so both processes execute their claim query simultaneously
        unlink($barrierFile);

        // Wait for both processes to terminate
        $proc1->wait();
        $proc2->wait();

        $this->assertTrue($proc1->isSuccessful(), "Process 1 failed: " . $proc1->getErrorOutput());
        $this->assertTrue($proc2->isSuccessful(), "Process 2 failed: " . $proc2->getErrorOutput());

        $out1 = json_decode($proc1->getOutput(), true);
        $out2 = json_decode($proc2->getOutput(), true);

        $this->assertIsArray($out1, "Process 1 did not output valid JSON: " . $proc1->getOutput());
        $this->assertIsArray($out2, "Process 2 did not output valid JSON: " . $proc2->getOutput());

        $this->assertNotEquals($out1['pid'], $out2['pid'], "Processes must have different PIDs.");
        $this->assertNotEquals($out1['connection_id'], $out2['connection_id'], "Processes must use different MySQL connection IDs.");

        $claimedCount = ($out1['claimed'] ? 1 : 0) + ($out2['claimed'] ? 1 : 0);
        $this->assertEquals(1, $claimedCount, "Under true multi-process concurrency, EXACTLY one process must acquire the cooldown claim.");

        // Database must reflect the claimed timestamp
        $row = DB::table('conversation_participants')
            ->where('conversation_id', $conv->id)
            ->where('user_id', $admin->id)
            ->first();
        $this->assertNotNull($row->last_email_notified_at);
    }

    /**
     * Test 4: Read-cursor monotonicity under out-of-order and concurrent updates on MySQL.
     * Stale requests cannot regress the cursor; newer timestamps always win.
     */
    public function test_read_cursor_monotonicity_under_out_of_order_and_concurrent_updates(): void
    {
        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();
        $conv = $this->createTrackedConversation($member, $admin);

        $initialTime = '2026-10-12 12:00:00';
        $partId = DB::table('conversation_participants')->insertGetId([
            'conversation_id' => $conv->id,
            'user_id' => $admin->id,
            'last_read_at' => $initialTime,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdo = $this->openIndependentPdo();

        // 1. Stale client request sends an older timestamp (11:30:00)
        $staleTime = '2026-10-12 11:30:00';
        $stmt = $pdo->prepare(
            "UPDATE conversation_participants
             SET last_read_at = :staleTime, updated_at = NOW()
             WHERE id = :id AND (last_read_at IS NULL OR last_read_at < :staleTimeCheck)"
        );
        $stmt->execute([
            'staleTime' => $staleTime,
            'staleTimeCheck' => $staleTime,
            'id' => $partId,
        ]);

        $this->assertEquals(0, $stmt->rowCount(), "Stale update must affect 0 rows.");
        $row = DB::table('conversation_participants')->find($partId);
        $this->assertEquals($initialTime, $row->last_read_at, "Cursor must not regress backwards.");

        // 2. Multi-process race: Process A with 12:15:00 vs Process B with 12:45:00
        $barrierFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cursor_race_' . uniqid() . '.barrier';
        touch($barrierFile);

        $workerScript = base_path('tests/Feature/ConcurrencyWorkers/dm_worker.php');
        $env = $this->workerEnv();

        $timeA = '2026-10-12 12:15:00';
        $timeB = '2026-10-12 12:45:00';

        $procA = new Process(['php', $workerScript, 'cursor', (string)$partId, $timeA, $barrierFile], null, $env);
        $procB = new Process(['php', $workerScript, 'cursor', (string)$partId, $timeB, $barrierFile], null, $env);

        $procA->start();
        $procB->start();

        usleep(300000);
        unlink($barrierFile);

        $procA->wait();
        $procB->wait();

        $this->assertTrue($procA->isSuccessful());
        $this->assertTrue($procB->isSuccessful());

        // Regardless of which process arrived first, the final database cursor must be the later timestamp
        $finalRow = DB::table('conversation_participants')->find($partId);
        $this->assertEquals($timeB, $finalRow->last_read_at, "Final read cursor must monotonically reflect the newest timestamp ({$timeB}).");
    }

    /**
     * Test 5: Duplicate conversation creation attempts for the same canonical pair are rejected by MySQL.
     */
    public function test_duplicate_conversation_pair_unique_constraint_enforced_by_mysql_innodb(): void
    {
        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();

        $userOneId = min($member->id, $admin->id);
        $userTwoId = max($member->id, $admin->id);

        $pdo1 = $this->openIndependentPdo();
        $pdo2 = $this->openIndependentPdo();

        $sql = "INSERT INTO conversations (uuid, user_one_id, user_two_id, created_at, updated_at) 
                VALUES (:uuid, :u1, :u2, NOW(), NOW())";

        $stmt1 = $pdo1->prepare($sql);
        $stmt1->execute([
            'uuid' => (string) Str::uuid(),
            'u1' => $userOneId,
            'u2' => $userTwoId,
        ]);
        $this->createdConversationIds[] = (int) $pdo1->lastInsertId();

        // Second connection attempts to insert the identical canonical pair
        $duplicateExceptionCaught = false;
        try {
            $stmt2 = $pdo2->prepare($sql);
            $stmt2->execute([
                'uuid' => (string) Str::uuid(),
                'u1' => $userOneId,
                'u2' => $userTwoId,
            ]);
            $this->createdConversationIds[] = (int) $pdo2->lastInsertId();
        } catch (\PDOException $e) {
            $duplicateExceptionCaught = true;
            // MySQL error code 23000 / 1062 (Duplicate entry for key 'conversations_participants_pair_unique')
            $this->assertEquals('23000', $e->getCode());
            $this->assertStringContainsString('conversations_participants_pair_unique', $e->getMessage());
        }

        $this->assertTrue($duplicateExceptionCaught, "MySQL must throw a unique constraint violation on duplicate conversation pair.");

        $count = DB::table('conversations')
            ->where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->count();
        $this->assertEquals(1, $count, "Exactly one conversation record can exist for a canonical participant pair.");
    }

    /**
     * Test 6: Duplicate participant insertion attempts are rejected by MySQL unique constraint.
     */
    public function test_duplicate_participant_unique_constraint_enforced_by_mysql_innodb(): void
    {
        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();
        $conv = $this->createTrackedConversation($member, $admin);

        $pdo1 = $this->openIndependentPdo();
        $pdo2 = $this->openIndependentPdo();

        $sql = "INSERT INTO conversation_participants (conversation_id, user_id, created_at, updated_at) 
                VALUES (:conv_id, :user_id, NOW(), NOW())";

        $stmt1 = $pdo1->prepare($sql);
        $stmt1->execute(['conv_id' => $conv->id, 'user_id' => $admin->id]);

        $duplicateExceptionCaught = false;
        try {
            $stmt2 = $pdo2->prepare($sql);
            $stmt2->execute(['conv_id' => $conv->id, 'user_id' => $admin->id]);
        } catch (\PDOException $e) {
            $duplicateExceptionCaught = true;
            $this->assertEquals('23000', $e->getCode());
            $this->assertStringContainsString('conv_part_unique', $e->getMessage());
        }

        $this->assertTrue($duplicateExceptionCaught, "MySQL must enforce conv_part_unique constraint on duplicate participant rows.");

        $count = DB::table('conversation_participants')
            ->where('conversation_id', $conv->id)
            ->where('user_id', $admin->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 7: Database transaction rollback leaves zero orphan messages or notifications on MySQL.
     */
    public function test_transaction_rollback_prevents_message_persistence_and_notification(): void
    {
        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();
        $conv = $this->createTrackedConversation($member, $admin);

        $messageBody = 'Message that should roll back completely';

        try {
            DB::transaction(function () use ($conv, $member, $messageBody) {
                DirectMessage::create([
                    'conversation_id' => $conv->id,
                    'sender_id' => $member->id,
                    'body' => $messageBody,
                ]);

                $conv->update([
                    'last_message_at' => now(),
                    'last_message_preview' => 'preview',
                ]);

                // Simulate catastrophic error before commit
                throw new \RuntimeException('Simulated transaction abort');
            });
        } catch (\RuntimeException $e) {
            // Expected rollback
        }

        $this->assertDatabaseMissing('direct_messages', [
            'conversation_id' => $conv->id,
            'body' => $messageBody,
        ]);

        $freshConv = $conv->fresh();
        $this->assertNull($freshConv->last_message_at);
        $this->assertNull($freshConv->last_message_preview);

        $this->assertEquals(0, DB::table('notifications')->where('user_id', $admin->id)->count());
    }

    /**
     * Test 8: Post-commit mail transport failure preserves message, in-app notification, and cooldown on MySQL.
     */
    public function test_post_commit_transport_failure_preserves_message_and_cooldown_on_mysql(): void
    {
        Log::spy();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'uuid' => (string) Str::uuid()]
        );
        if ($adminRole->wasRecentlyCreated) {
            $this->createdRoleIds[] = $adminRole->id;
        }

        $member = $this->createTrackedUser();
        $admin = $this->createTrackedUser();
        $admin->roles()->sync([$adminRole->id]);

        // Mock MailChannel to throw an exception with mock sensitive text
        $this->app->bind(MailChannel::class, function () {
            $mock = \Mockery::mock(MailChannel::class);
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('Connection failed: secret_user@smtp.sfu.ca secret_pass_xyz'));
            return $mock;
        });

        $messageText = 'Important direct message during SMTP outage';

        $res = $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $admin->id,
                'message' => $messageText,
            ]);

        // HTTP 201 must still be returned to client
        $res->assertStatus(201);
        $convId = $res->json('id');
        $this->createdConversationIds[] = $convId;

        // Message is committed and durable in MySQL
        $this->assertDatabaseHas('direct_messages', [
            'conversation_id' => $convId,
            'sender_id' => $member->id,
            'body' => $messageText,
        ]);

        // In-app notification is committed in MySQL
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => DirectMessageNotification::class,
        ]);

        // Email cooldown is active in MySQL
        $part = DB::table('conversation_participants')
            ->where('conversation_id', $convId)
            ->where('user_id', $admin->id)
            ->first();
        $this->assertNotNull($part->last_email_notified_at);

        // Verify sanitized logging without sensitive credentials
        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'DirectMessageNotification delivery failure',
                \Mockery::on(function ($context) {
                    $json = json_encode($context);
                    return isset($context['correlation_id'])
                        && Str::isUuid($context['correlation_id'])
                        && ($context['error_classification'] ?? '') === 'RuntimeException'
                        && !isset($context['error_message'])
                        && !str_contains($json, 'secret_pass_xyz')
                        && !str_contains($json, 'secret_user@smtp.sfu.ca');
                })
            );
    }
}
