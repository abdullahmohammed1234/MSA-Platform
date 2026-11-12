<?php

namespace App\Pm\Services;

use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PmICalendarService
{
    protected const HOST = 'projects.sfumsa.ca';
    protected const PRODID = '-//SFU MSA//Project Management System//EN';

    /**
     * Generate RFC 5545 iCalendar data stream.
     *
     * @param  Collection<int, PmProject>  $projects
     * @param  Collection<int, PmMilestone>  $milestones
     * @param  Collection<int, PmTask>  $tasks
     */
    public function generate(
        Collection $projects,
        Collection $milestones,
        Collection $tasks,
        string $calendarName = 'SFU MSA PMS'
    ): string {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calendarName),
            'X-WR-TIMEZONE:America/Vancouver',
        ];

        $nowStamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        // 1. Projects (Duration / Schedule)
        foreach ($projects as $project) {
            $deadline = $project->due_date;
            $startDate = $project->start_date;
            if (!$deadline && !$startDate) {
                continue;
            }

            $startCarbon = $startDate ? Carbon::parse($startDate) : Carbon::parse($deadline);
            $deadlineCarbon = $deadline ? Carbon::parse($deadline) : Carbon::parse($startDate);
            $dtStart = $startCarbon->format('Ymd');
            $dtEnd = $deadlineCarbon->copy()->addDay()->format('Ymd');

            $uid = "project-{$project->uuid}@" . self::HOST;
            $summary = "🏁 " . $project->name;

            $statusStr = match ($project->status) {
                ProjectStatus::Completed => 'COMPLETED',
                ProjectStatus::Cancelled => 'CANCELLED',
                default => 'CONFIRMED',
            };

            $priorityNum = match ($project->priority) {
                ProjectPriority::Urgent => 1,
                ProjectPriority::High => 3,
                ProjectPriority::Medium => 5,
                ProjectPriority::Low => 9,
                default => 5,
            };

            $descLines = [
                "Project: {$project->name} ({$project->code})",
                "Status: " . ($project->status instanceof \BackedEnum ? $project->status->value : $project->status),
                "Priority: " . ($project->priority instanceof \BackedEnum ? $project->priority->value : $project->priority),
            ];

            if ($project->description) {
                $descLines[] = "";
                $descLines[] = $project->description;
            }

            $descLines[] = "";
            $descLines[] = "Portal: https://" . self::HOST . "/projects/{$project->uuid}";

            $description = implode("\n", $descLines);

            $eventLines = [
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . $nowStamp,
                'DTSTART;VALUE=DATE:' . $dtStart,
                'DTEND;VALUE=DATE:' . $dtEnd,
                'SUMMARY:' . $this->escapeText($summary),
                'DESCRIPTION:' . $this->escapeText($description),
                'STATUS:' . $statusStr,
                'PRIORITY:' . $priorityNum,
            ];

            if ($project->created_at) {
                $eventLines[] = 'CREATED:' . Carbon::parse($project->created_at)->setTimezone('UTC')->format('Ymd\THis\Z');
            }
            if ($project->updated_at) {
                $eventLines[] = 'LAST-MODIFIED:' . Carbon::parse($project->updated_at)->setTimezone('UTC')->format('Ymd\THis\Z');
            }

            $eventLines[] = 'END:VEVENT';

            foreach ($eventLines as $el) {
                $lines[] = $this->foldLine($el);
            }
        }

        // 2. Milestones
        $projectsById = $projects->keyBy('id');
        foreach ($milestones as $milestone) {
            if (!$milestone->due_date) {
                continue;
            }

            $dueCarbon = Carbon::parse($milestone->due_date);
            $dtStart = $dueCarbon->format('Ymd');
            $dtEnd = $dueCarbon->copy()->addDay()->format('Ymd');

            $uid = "milestone-{$milestone->uuid}@" . self::HOST;
            $summary = "🎯 " . $milestone->title;

            $statusStr = match ($milestone->status) {
                MilestoneStatus::Reached => 'COMPLETED',
                default => 'CONFIRMED',
            };

            $project = $projectsById->get($milestone->project_id) ?? $milestone->project;
            $projectName = $project ? "{$project->name} ({$project->code})" : 'PMS Project';

            $descLines = [
                "Milestone: {$milestone->title}",
                "Project: {$projectName}",
                "Status: " . ($milestone->status instanceof \BackedEnum ? $milestone->status->value : $milestone->status),
            ];

            if ($milestone->description) {
                $descLines[] = "";
                $descLines[] = $milestone->description;
            }

            if ($project) {
                $descLines[] = "";
                $descLines[] = "Portal: https://" . self::HOST . "/projects/{$project->uuid}?tab=milestones";
            }

            $description = implode("\n", $descLines);

            $eventLines = [
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . $nowStamp,
                'DTSTART;VALUE=DATE:' . $dtStart,
                'DTEND;VALUE=DATE:' . $dtEnd,
                'SUMMARY:' . $this->escapeText($summary),
                'DESCRIPTION:' . $this->escapeText($description),
                'STATUS:' . $statusStr,
            ];

            if ($milestone->created_at) {
                $eventLines[] = 'CREATED:' . Carbon::parse($milestone->created_at)->setTimezone('UTC')->format('Ymd\THis\Z');
            }
            if ($milestone->updated_at) {
                $eventLines[] = 'LAST-MODIFIED:' . Carbon::parse($milestone->updated_at)->setTimezone('UTC')->format('Ymd\THis\Z');
            }

            $eventLines[] = 'END:VEVENT';

            foreach ($eventLines as $el) {
                $lines[] = $this->foldLine($el);
            }
        }

        // 3. Tasks
        foreach ($tasks as $task) {
            if (!$task->due_date) {
                continue;
            }

            $startCarbon = $task->start_date ? Carbon::parse($task->start_date) : Carbon::parse($task->due_date);
            $dueCarbon = Carbon::parse($task->due_date);

            $dtStart = $startCarbon->format('Ymd');
            $dtEnd = $dueCarbon->copy()->addDay()->format('Ymd');

            $uid = "task-{$task->uuid}@" . self::HOST;
            $summary = $task->title;

            $statusStr = match ($task->status) {
                TaskStatus::Completed => 'COMPLETED',
                TaskStatus::Cancelled => 'CANCELLED',
                TaskStatus::InProgress => 'IN-PROCESS',
                default => 'NEEDS-ACTION',
            };

            $rawPriority = $task->priority instanceof \BackedEnum ? $task->priority->value : (string) $task->priority;
            $priorityNum = match ($rawPriority) {
                'urgent' => 1,
                'high' => 3,
                'medium' => 5,
                'low' => 9,
                default => 5,
            };

            $project = $projectsById->get($task->project_id) ?? $task->project;
            $projectName = $project ? "{$project->name} ({$project->code})" : 'PMS Project';

            // Assignees list
            $assigneeNames = [];
            if ($task->relationLoaded('assignees') && $task->assignees->isNotEmpty()) {
                $assigneeNames = $task->assignees->pluck('name')->all();
            } elseif ($task->relationLoaded('assignee') && $task->assignee) {
                $assigneeNames[] = $task->assignee->name;
            }

            $descLines = [
                "Task: {$task->title}",
                "Project: {$projectName}",
                "Status: " . ($task->status instanceof \BackedEnum ? $task->status->value : $task->status),
                "Priority: {$rawPriority}",
            ];

            if (!empty($assigneeNames)) {
                $descLines[] = "Assignees: " . implode(', ', $assigneeNames);
            }

            if ($task->description) {
                $descLines[] = "";
                $descLines[] = $task->description;
            }

            if ($project) {
                $descLines[] = "";
                $descLines[] = "Portal: https://" . self::HOST . "/projects/{$project->uuid}?task={$task->uuid}";
            }

            $description = implode("\n", $descLines);

            $eventLines = [
                'BEGIN:VEVENT',
                'UID:' . $uid,
                'DTSTAMP:' . $nowStamp,
                'DTSTART;VALUE=DATE:' . $dtStart,
                'DTEND;VALUE=DATE:' . $dtEnd,
                'SUMMARY:' . $this->escapeText($summary),
                'DESCRIPTION:' . $this->escapeText($description),
                'STATUS:' . $statusStr,
                'PRIORITY:' . $priorityNum,
            ];

            if ($task->created_at) {
                $eventLines[] = 'CREATED:' . Carbon::parse($task->created_at)->setTimezone('UTC')->format('Ymd\THis\Z');
            }
            if ($task->updated_at) {
                $eventLines[] = 'LAST-MODIFIED:' . Carbon::parse($task->updated_at)->setTimezone('UTC')->format('Ymd\THis\Z');
            }

            $eventLines[] = 'END:VEVENT';

            foreach ($eventLines as $el) {
                $lines[] = $this->foldLine($el);
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Fold an iCalendar line to a maximum of 75 octets (bytes) according to RFC 5545.
     */
    public function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $currentLine = '';
        $currentLength = 0;
        $maxFirstLineLength = 75;
        $maxNextLineLength = 74; // 1 space prefix + 74 bytes = 75 octets

        $chars = mb_str_split($line, 1, 'UTF-8');
        $isFirstLine = true;

        foreach ($chars as $char) {
            $charLen = strlen($char);
            $limit = $isFirstLine ? $maxFirstLineLength : $maxNextLineLength;

            if ($currentLength + $charLen > $limit) {
                $result .= ($result === '' ? '' : "\r\n ") . $currentLine;
                $currentLine = $char;
                $currentLength = $charLen;
                $isFirstLine = false;
            } else {
                $currentLine .= $char;
                $currentLength += $charLen;
            }
        }

        if ($currentLine !== '') {
            $result .= ($result === '' ? '' : "\r\n ") . $currentLine;
        }

        return $result;
    }

    /**
     * Escape special characters in text values per RFC 5545 section 3.3.11.
     */
    public function escapeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\n", '\n', $text);

        return $text;
    }
}

