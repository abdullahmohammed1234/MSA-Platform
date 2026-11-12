<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Project Progress Report - {{ $project->name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e1e1e;
            line-height: 1.45;
            margin: 0;
            padding: 24px;
            background-color: #fffbf4;
        }
        .header {
            border-bottom: 3px solid #640c0e;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #640c0e;
            font-size: 24px;
            margin: 0 0 4px 0;
            font-family: Georgia, serif;
        }
        .header p {
            color: #6b7280;
            margin: 0;
            font-size: 12px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 5px 8px;
            font-size: 11px;
            border-bottom: 1px solid #ebe8de;
        }
        .meta-label {
            font-weight: bold;
            color: #6b7280;
            width: 22%;
        }
        .meta-value {
            color: #1e1e1e;
        }
        .summary-grid {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 25%;
            padding: 6px;
        }
        .metric-card {
            background-color: #ffffff;
            border: 1px solid #ebe8de;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .metric-title {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #6b7280;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .metric-value {
            font-size: 16px;
            font-weight: bold;
            color: #640c0e;
        }
        .section-title {
            color: #640c0e;
            font-size: 13px;
            font-weight: bold;
            border-bottom: 1.5px solid #ebe8de;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 10px;
            background-color: #ffffff;
        }
        .data-table th {
            background-color: #f7f5ef;
            color: #640c0e;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #ebe8de;
        }
        .data-table td {
            padding: 5px 8px;
            border: 1px solid #ebe8de;
            color: #374151;
        }
        .data-table tr:nth-child(even) td {
            background-color: #faf9f6;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-completed { background-color: #d1fae5; color: #065f46; }
        .badge-in-progress { background-color: #dbeafe; color: #1e40af; }
        .badge-todo { background-color: #fef3c7; color: #92400e; }
        .badge-overdue { background-color: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #ebe8de;
            padding-top: 8px;
            text-align: right;
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $project->name }}</h1>
        <p>Project Progress & Intelligence Report &bull; SFU Muslim Students' Association</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Project Code:</td>
            <td class="meta-value"><strong>{{ $project->code ?? 'N/A' }}</strong></td>
            <td class="meta-label">Department:</td>
            <td class="meta-value">{{ $project->department->name ?? 'Universal' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Status:</td>
            <td class="meta-value">{{ ucfirst(str_replace('_', ' ', $project->status->value ?? $project->status)) }}</td>
            <td class="meta-label">Owner / Lead:</td>
            <td class="meta-value">{{ $project->owner->name ?? 'Unassigned' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Timeline Window:</td>
            <td class="meta-value">
                {{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('M d, Y') : 'Open' }}
                &ndash;
                {{ $project->target_date ? \Carbon\Carbon::parse($project->target_date)->format('M d, Y') : 'No Target' }}
            </td>
            <td class="meta-label">Generated On:</td>
            <td class="meta-value">{{ now()->format('Y-m-d H:i') }} (UTC)</td>
        </tr>
    </table>

    <!-- Key Metrics Grid -->
    <table class="summary-grid">
        <tr>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Completion Rate</div>
                    <div class="metric-value">{{ $metrics['completion_rate'] }}%</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Total Tasks</div>
                    <div class="metric-value">{{ $metrics['total_tasks'] }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Overdue Tasks</div>
                    <div class="metric-value" style="color: {{ $metrics['overdue_tasks'] > 0 ? '#ea2128' : '#10b981' }};">
                        {{ $metrics['overdue_tasks'] }}
                    </div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Remaining Effort</div>
                    <div class="metric-value">{{ $metrics['remaining_hours'] }}h</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Milestones Section -->
    @if(count($milestones) > 0)
        <div class="section-title">Milestones ({{ count($milestones) }})</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Milestone Title</th>
                    <th style="width: 20%;">Target Date</th>
                    <th style="width: 20%;">Status</th>
                    <th style="width: 20%;">Progress</th>
                </tr>
            </thead>
            <tbody>
                @foreach($milestones as $ms)
                    @php
                        $msStatus = $ms->status instanceof \BackedEnum ? $ms->status->value : (string) ($ms->status ?? 'pending');
                    @endphp
                    <tr>
                        <td><strong>{{ $ms->title }}</strong></td>
                        <td>{{ $ms->due_date ? \Carbon\Carbon::parse($ms->due_date)->format('Y-m-d') : 'No Date' }}</td>
                        <td>
                            @if($msStatus === 'completed')
                                <span class="badge badge-completed">Completed</span>
                            @elseif($ms->due_date && \Carbon\Carbon::parse($ms->due_date)->isPast())
                                <span class="badge badge-overdue">Overdue</span>
                            @else
                                <span class="badge badge-in-progress">{{ ucfirst(str_replace('_', ' ', $msStatus)) }}</span>
                            @endif
                        </td>
                        <td>{{ $ms->tasks_count ? round(($ms->completed_tasks_count / $ms->tasks_count) * 100) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- Active Tasks Summary -->
    <div class="section-title">Tasks (Showing {{ count($tasks) }} of {{ $totalTasksCount ?? count($tasks) }})</div>
    @if(!empty($isTasksTruncated))
        <div style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 6px 10px; margin-bottom: 10px; border-radius: 4px; font-size: 9px;">
            <strong>Notice:</strong> Tasks roster truncated to first {{ count($tasks) }} tasks (capped at 500).
        </div>
    @endif
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 32%;">Task</th>
                <th style="width: 18%;">Assignee</th>
                <th style="width: 14%;">Status</th>
                <th style="width: 12%;">Priority</th>
                <th style="width: 14%;">Due Date</th>
                <th style="width: 10%;">Est. Hrs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->assignee->name ?? 'Unassigned' }}</td>
                    <td>
                        <span class="badge badge-{{ str_replace('_', '-', $task->status->value ?? $task->status) }}">
                            {{ ucfirst(str_replace('_', ' ', $task->status->value ?? $task->status)) }}
                        </span>
                    </td>
                    <td>{{ ucfirst($task->priority->value ?? $task->priority) }}</td>
                    <td>
                        @if($task->due_date)
                            {{ \Carbon\Carbon::parse($task->due_date)->format('Y-m-d') }}
                            @if(\Carbon\Carbon::parse($task->due_date)->isPast() && ($task->status->value ?? $task->status) !== 'completed')
                                <span style="color: #ea2128; font-weight: bold;">!</span>
                            @endif
                        @else
                            &ndash;
                        @endif
                    </td>
                    <td>{{ $task->estimated_hours ? $task->estimated_hours . 'h' : '&ndash;' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #9ca3af; padding: 12px;">No tasks found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        SFU MSA Project Management System &bull; Generated by {{ $generatedBy->name ?? 'System' }} &bull; Confidential &bull; Page 1
    </div>
</body>
</html>