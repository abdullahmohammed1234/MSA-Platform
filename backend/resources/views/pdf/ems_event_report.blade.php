<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Event Summary Report - {{ $event->name }}</title>
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
            border-bottom: 3px solid #640c0e;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #640c0e;
            font-size: 26px;
            margin: 0 0 5px 0;
            font-family: Georgia, serif;
        }
        .header p {
            color: #5a5d61;
            margin: 0;
            font-size: 13px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 12px;
            border-bottom: 1px solid #ebe8de;
        }
        .meta-label {
            font-weight: bold;
            color: #5a5d61;
            width: 25%;
        }
        .meta-value {
            color: #1e1e1e;
        }
        .summary-grid {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 25%;
            padding: 8px;
        }
        .metric-card {
            background-color: #ffffff;
            border: 1px solid #ebe8de;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .metric-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #5a5d61;
            margin-bottom: 5px;
        }
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #640c0e;
        }
        .section-title {
            color: #640c0e;
            font-size: 15px;
            border-bottom: 2px solid #ebe8de;
            padding-bottom: 4px;
            margin-top: 30px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        table.data-table th {
            background-color: #640c0e;
            color: #ffffff;
            font-size: 11px;
            text-align: left;
            padding: 7px 9px;
            font-weight: 500;
        }
        table.data-table td {
            padding: 7px 9px;
            border-bottom: 1px solid #ebe8de;
            font-size: 11px;
            color: #1e1e1e;
        }
        table.data-table tr:nth-child(even) td {
            background-color: rgba(235, 232, 222, 0.2);
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9px;
            color: #5a5d61;
            border-top: 1px solid #ebe8de;
            padding-top: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>SFU Muslim Students Association</h1>
        <p>Event Report: <strong>{{ $event->name }}</strong></p>
        <p style="font-size: 10px; margin-top: 4px;">
            Report Generation Date: {{ $generated_at }} | Version: {{ $version }}
        </p>
    </div>

    <div class="section-title">Event Information</div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Category</td>
            <td class="meta-value">{{ $event->category->name ?? 'None' }}</td>
            <td class="meta-label">Location</td>
            <td class="meta-value">{{ $event->location ?? 'TBD' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Start Time</td>
            <td class="meta-value">{{ $event->start_at ? $event->start_at->format('M d, Y H:i') : 'N/A' }}</td>
            <td class="meta-label">End Time</td>
            <td class="meta-value">{{ $event->end_at ? $event->end_at->format('M d, Y H:i') : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Organizer</td>
            <td class="meta-value">{{ $event->organizer->name ?? 'None' }}</td>
            <td class="meta-label">Capacity</td>
            <td class="meta-value">{{ $event->capacity !== null ? $event->capacity . ' attendees' : 'Unlimited' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Status</td>
            <td class="meta-value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $event->status->value) }}</td>
            <td class="meta-label">Waitlist Status</td>
            <td class="meta-value">{{ $event->waitlist_enabled ? 'Enabled' : 'Disabled' }}</td>
        </tr>
    </table>

    <div class="section-title">Key Performance Indicators</div>
    <table class="summary-grid">
        <tr>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Registrations</div>
                    <div class="metric-value">{{ number_format($kpis['total_registrations']) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Checked In</div>
                    <div class="metric-value">{{ number_format($kpis['checked_in']) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">No Shows</div>
                    <div class="metric-value">{{ number_format($kpis['no_shows']) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Attendance Rate</div>
                    <div class="metric-value">{{ $kpis['attendance_rate'] }}%</div>
                </div>
            </td>
        </tr>
        @if($show_financial)
        <tr>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Gross Revenue</div>
                    <div class="metric-value">${{ number_format($kpis['gross_revenue'], 2) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Refunds</div>
                    <div class="metric-value">${{ number_format($kpis['refunds'], 2) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Net Revenue</div>
                    <div class="metric-value">${{ number_format($kpis['net_revenue'], 2) }}</div>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <div class="metric-title">Waitlist Size</div>
                    <div class="metric-value">{{ number_format($kpis['waitlist_size']) }}</div>
                </div>
            </td>
        </tr>
        @endif
    </table>

    @if(!empty($sections['ticket_sales']) && !empty($charts['ticket_performance']))
    <div class="section-title">Ticket Sales Overview</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Ticket Type</th>
                <th>Price</th>
                <th>Capacity</th>
                <th>Sold</th>
                <th>Remaining</th>
                @if($show_financial)
                <th>Revenue</th>
                @endif
                <th>Sell-Through</th>
            </tr>
        </thead>
        <tbody>
            @foreach($charts['ticket_performance'] as $perf)
            <tr>
                <td>{{ $perf['name'] }}</td>
                <td>${{ number_format($perf['price'], 2) }}</td>
                <td>{{ $perf['capacity'] !== null ? number_format($perf['capacity']) : 'Unlimited' }}</td>
                <td>{{ number_format($perf['sold']) }}</td>
                <td>{{ $perf['remaining'] !== null ? number_format($perf['remaining']) : 'Unlimited' }}</td>
                @if($show_financial)
                <td>${{ number_format($perf['revenue'], 2) }}</td>
                @endif
                <td>{{ $perf['sell_through'] !== null ? $perf['sell_through'] . '%' : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($sections['registrations']) && !empty($data['registrations']))
    <div class="page-break"></div>
    <div class="section-title">Attendees & Registrations</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Reference</th>
                <th>Attendee Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Ticket Type</th>
                <th>Status</th>
                <th>Registered At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['registrations'] as $reg)
            <tr>
                <td>{{ $reg->reference }}</td>
                <td>{{ $reg->attendee_name }}</td>
                <td>{{ $reg->attendee_email }}</td>
                <td>{{ $reg->attendee_phone ?? 'N/A' }}</td>
                <td>{{ $reg->ticketType->name ?? 'N/A' }}</td>
                <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $reg->status->value) }}</td>
                <td>{{ $reg->registered_at ? $reg->registered_at->format('Y-m-d H:i') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($show_financial && !empty($sections['payments']) && !empty($data['payments']))
    <div class="page-break"></div>
    <div class="section-title">Payments Log</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Registration Ref</th>
                <th>Amount</th>
                <th>Amount Refunded</th>
                <th>Status</th>
                <th>Paid At</th>
                <th>Provider</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['payments'] as $payment)
            <tr>
                <td>{{ $payment->provider_payment_id ?? $payment->uuid }}</td>
                <td>{{ $payment->registration->reference ?? 'N/A' }}</td>
                <td>${{ number_format($payment->amount, 2) }}</td>
                <td>${{ number_format($payment->amount_refunded, 2) }}</td>
                <td style="text-transform: capitalize;">{{ $payment->status->value }}</td>
                <td>{{ $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i') : '' }}</td>
                <td style="text-transform: capitalize;">{{ $payment->provider->value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($sections['waitlist']) && !empty($data['waitlist']))
    <div class="page-break"></div>
    <div class="section-title">Waitlist Log</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Position</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Qty</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['waitlist'] as $entry)
            <tr>
                <td>{{ $entry->position }}</td>
                <td>{{ $entry->attendee_name }}</td>
                <td>{{ $entry->attendee_email }}</td>
                <td>{{ $entry->attendee_phone ?? 'N/A' }}</td>
                <td>{{ $entry->quantity }}</td>
                <td style="text-transform: capitalize;">{{ $entry->status->value }}</td>
                <td>{{ $entry->created_at ? $entry->created_at->format('Y-m-d H:i') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($sections['check_ins']) && !empty($data['check_ins']))
    <div class="page-break"></div>
    <div class="section-title">Check-ins Log</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Ticket Code</th>
                <th>Holder Name</th>
                <th>Email</th>
                <th>Checked In At</th>
                <th>Method</th>
                <th>Checked In By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['check_ins'] as $check)
            <tr>
                <td>{{ $check->ticket->code ?? 'N/A' }}</td>
                <td>{{ $check->ticket->holder_name ?? ($check->registration->attendee_name ?? 'N/A') }}</td>
                <td>{{ $check->ticket->holder_email ?? ($check->registration->attendee_email ?? 'N/A') }}</td>
                <td>{{ $check->checked_in_at ? $check->checked_in_at->format('Y-m-d H:i:s') : '' }}</td>
                <td style="text-transform: capitalize;">{{ $check->method->value }}</td>
                <td>{{ $check->checkedInBy->name ?? 'System' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        SFU Muslim Students Association Platform &copy; {{ date('Y') }}. Generated on behalf of {{ $user->name }}. This report is strictly confidential.
    </div>

</body>
</html>
