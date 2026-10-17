<?php

/**
 * Worker script for multi-process MySQL concurrency verification.
 *
 * Usage:
 * php dm_worker.php claim <conversation_id> <user_id> <barrier_file>
 * php dm_worker.php cursor <participant_id> <target_timestamp> <barrier_file>
 */

require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\DirectMessagingAuthorizationService;
use Illuminate\Support\Facades\DB;

$action = $argv[1] ?? null;
$barrierFile = $argv[4] ?? ($argv[3] ?? null);

// Ensure we are connected to MySQL test database
$connection = config('database.default');
$dbName = (string) config("database.connections.{$connection}.database");
if ($connection !== 'mysql' || !str_ends_with($dbName, '_test')) {
    echo json_encode([
        'error' => "Safety guard failed: Invalid database '{$dbName}' on connection '{$connection}'"
    ]);
    exit(1);
}

// Bounded wait on synchronization barrier (max 5 seconds)
if ($barrierFile) {
    $startTime = microtime(true);
    while (file_exists($barrierFile)) {
        if ((microtime(true) - $startTime) > 5.0) {
            break;
        }
        usleep(1000); // 1ms
    }
}

$connId = DB::selectOne('SELECT CONNECTION_ID() as id')->id;

if ($action === 'claim') {
    $conversationId = (int) $argv[2];
    $userId = (int) $argv[3];
    $barrierFile = $argv[4] ?? null;

    $conv = Conversation::find($conversationId);
    $user = User::find($userId);

    if (!$conv || !$user) {
        echo json_encode(['error' => 'Conversation or user not found']);
        exit(1);
    }

    $claimed = DirectMessagingAuthorizationService::claimEmailNotification($conv, $user);

    echo json_encode([
        'pid' => getmypid(),
        'connection_id' => $connId,
        'action' => 'claim',
        'claimed' => $claimed,
    ]);
    exit(0);
}

if ($action === 'cursor') {
    $participantId = (int) $argv[2];
    $targetTime = $argv[3];
    $barrierFile = $argv[4] ?? null;

    $affected = DB::table('conversation_participants')
        ->where('id', $participantId)
        ->where(function ($q) use ($targetTime) {
            $q->whereNull('last_read_at')
              ->orWhere('last_read_at', '<', $targetTime);
        })
        ->update([
            'last_read_at' => $targetTime,
            'updated_at' => now(),
        ]);

    echo json_encode([
        'pid' => getmypid(),
        'connection_id' => $connId,
        'action' => 'cursor',
        'affected' => $affected,
        'target_time' => $targetTime,
    ]);
    exit(0);
}

echo json_encode(['error' => 'Unknown action']);
exit(1);
