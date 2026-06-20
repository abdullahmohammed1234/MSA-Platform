<?php

namespace App\Services\Queue;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Cron\CronExpression;

class SchedulerService
{
    /**
     * Get list of all registered scheduled events.
     */
    public function getScheduledTasks(): array
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();
        $tasks = [];

        foreach ($events as $index => $event) {
            $description = $event->description ?? 'Callback / Anonymous Task';
            $command = $event->command ?? 'Unknown';

            // Try to extract the job class name if it's a job event
            if (str_contains($command, 'schedule:run') || $command === 'Unknown') {
                if (isset($event->callback) && is_string($event->callback)) {
                    $description = $event->callback;
                } elseif (isset($event->callback) && is_object($event->callback)) {
                    // It might be a closure or job wrapper, try parsing description
                    if (property_exists($event, 'job') && $event->job) {
                        $description = get_class($event->job);
                    }
                }
            }

            // Fallback description based on command parameters
            if ($command !== 'Unknown' && $description === 'Callback / Anonymous Task') {
                $description = "Command: " . basename($command);
            }

            // Determine next run date
            $nextRun = 'N/A';
            try {
                $cron = new CronExpression($event->expression);
                $nextRun = Carbon::instance($cron->getNextRunDate())->toDateTimeString();
            } catch (\Exception $e) {
                // Ignore parsing errors
            }

            $tasks[] = [
                'id' => $index,
                'command' => $command,
                'expression' => $event->expression,
                'description' => $description,
                'timezone' => $event->timezone ?? config('app.timezone', 'UTC'),
                'next_run_at' => $nextRun,
                'interval_description' => $this->cronToHuman($event->expression),
            ];
        }

        return $tasks;
    }

    /**
     * Manually execute a scheduled task immediately in the background.
     */
    public function runScheduledTask(int $id): bool
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        if (!isset($events[$id])) {
            return false;
        }

        $event = $events[$id];
        Log::info("Manually triggering scheduled task ID: {$id} ({$event->description})");

        // Run the event callbacks/commands asynchronously/immediately
        try {
            $event->run(app());
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to run scheduled task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert cron expression to human readable string.
     */
    private function cronToHuman(string $expression): string
    {
        $parts = explode(' ', $expression);
        if (count($parts) < 5) {
            return $expression;
        }

        $min = $parts[0];
        $hour = $parts[1];
        $dom = $parts[2];
        $month = $parts[3];
        $dow = $parts[4];

        if ($min === '*' && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Every minute';
        }

        if ($min === '0' && $hour === '0' && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Daily at midnight';
        }

        if ($min === '*/5' && $hour === '*' && $dom === '*' && $month === '*') {
            return 'Every 5 minutes';
        }

        if ($dom === '*' && $month === '*' && $dow === '*') {
            return "Daily at " . sprintf("%02d:%02d", $hour, $min);
        }

        if ($dom === '*' && $month === '*') {
            $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $dayName = isset($daysOfWeek[$dow]) ? $daysOfWeek[$dow] : 'Day ' . $dow;
            return "Weekly on {$dayName} at " . sprintf("%02d:%02d", $hour, $min);
        }

        if ($month === '*') {
            if ($dom === '28' || $dom === '29' || $dom === '30' || $dom === '31' || str_contains($expression, 'lastDayOfMonth')) {
                return "Monthly on the last day of month at " . sprintf("%02d:%02d", $hour, $min);
            }
            return "Monthly on day {$dom} at " . sprintf("%02d:%02d", $hour, $min);
        }

        return $expression;
    }
}
