<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Schedule;

/**
 * Snaps every scheduled task's minute field onto the grid that the host cron can
 * actually invoke `schedule:run` on. `schedule:run` only runs tasks due in the exact
 * minute it is invoked, so with cron every N minutes a task at minute 55 or 59 would
 * otherwise never run.
 */
final class ScheduleCadence
{
    public const VALID_INTERVALS = [1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30, 60];

    public static function isSupported(int $interval): bool
    {
        return in_array($interval, self::VALID_INTERVALS, true);
    }

    public static function align(Schedule $schedule, int $interval): void
    {
        if ($interval <= 1 || ! in_array($interval, self::VALID_INTERVALS, true)) {
            return;
        }

        foreach ($schedule->events() as $event) {
            $fields = preg_split('/\s+/', trim($event->expression));
            $fields[0] = self::alignMinuteField($fields[0], $interval);
            $event->expression = implode(' ', $fields);
        }
    }

    public static function alignMinuteField(string $field, int $interval): string
    {
        if ($field === '*') {
            return $interval === 60 ? '0' : "*/{$interval}";
        }

        if (preg_match('/^\*\/(\d+)$/', $field, $m)) {
            $step = (int) $m[1];
            for ($candidate = $interval; $candidate <= 60; $candidate += $interval) {
                if ($candidate >= $step && 60 % $candidate === 0) {
                    return $candidate === 60 ? '0' : "*/{$candidate}";
                }
            }

            return "*/{$interval}";
        }

        if (preg_match('/^\d+(,\d+)*$/', $field)) {
            $minutes = array_map(fn ($m) => (int) $m - ((int) $m % $interval), explode(',', $field));
            $minutes = array_values(array_unique($minutes));
            sort($minutes);

            return implode(',', $minutes);
        }

        return $field;
    }
}
