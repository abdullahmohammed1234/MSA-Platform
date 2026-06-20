<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e1e1e;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
            background-color: #fffbf4;
        }
        .header {
            border-bottom: 2px solid #ebe8de;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            color: #640c0e;
            font-size: 24px;
            margin: 0 0 5px 0;
        }
        .header p {
            color: #5a5d61;
            margin: 0;
            font-size: 14px;
        }
        .summary-grid {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }
        .metric-card {
            background-color: #ffffff;
            border: 1px solid #ebe8de;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .metric-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #5a5d61;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #640c0e;
        }
        .section-title {
            color: #640c0e;
            font-size: 16px;
            border-bottom: 1px solid #ebe8de;
            padding-bottom: 5px;
            margin-top: 30px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table.data-table th {
            background-color: #640c0e;
            color: #ffffff;
            font-size: 12px;
            text-align: left;
            padding: 8px 10px;
            font-weight: 500;
        }
        table.data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ebe8de;
            font-size: 12px;
            color: #1e1e1e;
        }
        table.data-table tr:nth-child(even) td {
            background-color: rgba(235, 232, 222, 0.2);
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #5a5d61;
            border-top: 1px solid #ebe8de;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>SFU MSA Platform Analytics</h1>
        <p>{{ $title }} (Period: {{ ucfirst($period) }})</p>
        <p style="font-size: 11px; margin-top: 5px;">Report Generated At: {{ $generated_at }}</p>
    </div>

    <div class="section-title">Key Performance Indicators</div>
    
    <table class="summary-grid">
        <tr>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Unique Visitors</div>
                    <div class="metric-value">{{ number_format($metrics['website_visitors_unique_daily']['total'] ?? 0) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Page Views</div>
                    <div class="metric-value">{{ number_format($metrics['website_page_views_daily']['total'] ?? 0) }}</div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Active Academy Learners</div>
                    <div class="metric-value">{{ number_format($metrics['academy_active_learners_daily']['total'] ?? 0) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Course Completion Rate</div>
                    <div class="metric-value">{{ number_format($metrics['academy_course_completion_rate']['avg'] ?? 0, 1) }}%</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">Detailed Metrics History</div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Metric Category</th>
                <th>Aggregated Total</th>
                <th>Daily Average</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Unique Website Visitors</td>
                <td>{{ number_format($metrics['website_visitors_unique_daily']['total'] ?? 0) }}</td>
                <td>{{ number_format($metrics['website_visitors_unique_daily']['avg'] ?? 0, 1) }}</td>
            </tr>
            <tr>
                <td>Total Page Views</td>
                <td>{{ number_format($metrics['website_page_views_daily']['total'] ?? 0) }}</td>
                <td>{{ number_format($metrics['website_page_views_daily']['avg'] ?? 0, 1) }}</td>
            </tr>
            <tr>
                <td>Active Academy Learners (Daily Avg)</td>
                <td>-</td>
                <td>{{ number_format($metrics['academy_active_learners_daily']['avg'] ?? 0, 1) }}</td>
            </tr>
            <tr>
                <td>Certificates Issued (Cumulative)</td>
                <td>{{ number_format($metrics['academy_certificates_issued']['total'] ?? 0) }}</td>
                <td>-</td>
            </tr>
            <tr>
                <td>Event Registration Conversion Rate</td>
                <td>-</td>
                <td>{{ number_format($metrics['events_registrations_rate']['avg'] ?? 0, 1) }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        SFU Muslim Students Association Platform &copy; {{ date('Y') }}. This report is confidential and intended solely for authorized administrators.
    </div>

</body>
</html>
