<?php

namespace App\Listeners;

use App\Platform\Enums\AlertSeverity;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The scheduler already isolates a failing task from the others; this makes
 * the failure visible without logging the exception message (which can carry
 * recipients or provider responses).
 */
class ScheduledTaskFailureListener
{
    public function __construct(private readonly PlatformAlertService $alerts)
    {
    }

    public function handle(ScheduledTaskFailed $event): void
    {
        $task = self::taskName($event->task);
        $exception = $event->exception::class;

        Log::error('scheduler.task_failed', [
            'task' => $task,
            'exception' => $exception,
        ]);

        try {
            $this->alerts->createDeduplicatedAlert(
                key: 'scheduler.task_failed.'.Str::slug($task),
                application: 'system',
                severity: AlertSeverity::WARNING,
                title: 'Scheduled Task Failed',
                message: "Scheduled task '{$task}' failed ({$exception}).",
                context: ['task' => $task, 'exception' => $exception]
            );
        } catch (Throwable $e) {
            Log::error('scheduler.task_failed_alert_unavailable', ['task' => $task, 'exception' => $e::class]);
        }
    }

    public static function taskName(\Illuminate\Console\Scheduling\Event $task): string
    {
        if ($task->description) {
            return Str::limit($task->description, 120, '');
        }

        // Strip the PHP binary and artisan path, keep the command and its options.
        $command = (string) $task->command;
        if (preg_match("/artisan['\"]?\s+(.+)$/", $command, $m)) {
            $command = $m[1];
        }

        return Str::limit(trim(str_replace(["'", '"'], '', $command)), 120, '');
    }
}
