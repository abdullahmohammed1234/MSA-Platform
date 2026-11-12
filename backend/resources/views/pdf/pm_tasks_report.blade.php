<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
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
            font-size: 22px;
            margin: 0 0 4px 0;
            font-family: Georgia, serif;
        }
        .header p {
            color: #6b7280;
            margin: 0;
            font-size: 11px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 9.5px;
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
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-completed { background-color: #d1fae5; color: #065f46; }
        .badge-in-progress { background-color: #dbeafe; color: #1e40af; }
        .badge-todo { background-color: #fef3c7; color: #92400e; }
        .badge-review { background-color: #ede9fe; color: #5b21b6; }
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
        <h1>{{ $title }}</h1>
        <p>Generated on {{ now()->format('Y-m-d H:i') }} UTC &bull; Showing {{ count($tasks) }} of {{ $totalMatching ?? count($tasks) }} tasks &bull; SFU MSA PMS</p>
    </div>

    @if(!empty($isTruncated))
        <div style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 7px 10px; margin-bottom: 14px; border-radius: 4px; font-size: 9.5px; line-height: 1.4;">
            <strong>Notice:</strong> Export capped at {{ $limit ?? 1000 }} records. Displaying first {{ count($tasks) }} of {{ $totalMatching }} matching tasks. Apply filters (status, priority, date range) to view remaining records.
        </div>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Task Title</th>
                <th style="width: 20%;">Project</th>
                <th style="width: 15%;">Assignee</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 10%;">Priority</th>
                <th style="width: 10%;">Due Date</th>
                <th style="width: 8%;">Est. Hrs</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $t)
                <tr>
                    <td><strong>{{ $t->title }}</strong></td>
                    <td>{{ $t->project->name ?? 'N/A' }}</td>
                    <td>{{ $t->assignee->name ?? 'Unassigned' }}</td>
                    <td>
                        <span class="badge badge-{{ str_replace('_', '-', $t->status->value ?? $t->status) }}">
                            {{ ucfirst(str_replace('_', ' ', $t->status->value ?? $t->status)) }}
                        </span>
                    </td>
                    <td>{{ ucfirst($t->priority->value ?? $t->priority) }}</td>
                    <td>{{ $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('Y-m-d') : '-' }}</td>
                    <td>{{ $t->estimated_hours ? $t->estimated_hours . 'h' : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #9ca3af; padding: 12px;">No tasks found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        SFU MSA Project Management System &bull; Confidential &bull; Option B Compliant
    </div>
</body>
</html>
