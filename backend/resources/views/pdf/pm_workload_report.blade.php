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
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
        }
        .badge-normal { background-color: #d1fae5; color: #065f46; }
        .badge-near { background-color: #fef3c7; color: #92400e; }
        .badge-overloaded { background-color: #fee2e2; color: #991b1b; }
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
        <p>Period: {{ $startDate }} to {{ $endDate }} &bull; Generated: {{ now()->format('Y-m-d H:i') }} UTC &bull; SFU MSA PMS</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Contributor</th>
                <th style="width: 25%;">Email</th>
                <th style="width: 12%;">Weekly Cap</th>
                <th style="width: 12%;">Allocated</th>
                <th style="width: 12%;">Active Tasks</th>
                <th style="width: 14%;">Utilization</th>
            </tr>
        </thead>
        <tbody>
            @forelse($workloadData as $item)
                @php
                    $rate = $item['utilization_rate'] ?? 0;
                    $badgeClass = $rate > 100 ? 'badge-overloaded' : ($rate >= 80 ? 'badge-near' : 'badge-normal');
                @endphp
                <tr>
                    <td><strong>{{ $item['user']['name'] ?? 'Team Member' }}</strong></td>
                    <td>{{ $item['user']['email'] ?? 'N/A' }}</td>
                    <td>{{ $item['weekly_capacity_hours'] ?? 10 }}h/wk</td>
                    <td>{{ $item['total_allocated_hours'] ?? 0 }}h</td>
                    <td>{{ $item['active_tasks_count'] ?? 0 }}</td>
                    <td>
                        <span class="badge {{ $badgeClass }}">
                            {{ $rate }}%
                            @if($rate > 100) (Overloaded) @endif
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #9ca3af; padding: 12px;">No contributor workload recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        SFU MSA Project Management System &bull; Confidential &bull; Option B Compliant
    </div>
</body>
</html>
