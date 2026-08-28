<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ALERT: EMS Email Failure</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <!-- Header -->
                <tr>
                    <td style="background:#dc2626;color:#ffffff;padding:20px 24px;">
                        <h2 style="margin:0;font-size:18px;font-weight:600;letter-spacing:0.02em;">
                            EMS Registration Email Delivery Failure
                        </h2>
                    </td>
                </tr>
                <!-- Content -->
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#334155;">
                            Assalamu alaikum,
                        </p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#334155;">
                            An automated registration confirmation email has failed to deliver. Details of the failure are provided below:
                        </p>

                        <!-- Details Table -->
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #f1f5f9;border-radius:6px;background:#f8fafc;padding:16px;margin-bottom:24px;font-size:14px;line-height:1.6;">
                            <tr>
                                <td style="font-weight:600;color:#475569;width:160px;vertical-align:top;padding:4px 0;">Event:</td>
                                <td style="color:#0f172a;padding:4px 0;">{{ $eventName }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Registration Ref:</td>
                                <td style="color:#0f172a;padding:4px 0;"><code>{{ $registrationRef }}</code></td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Attendee Name:</td>
                                <td style="color:#0f172a;padding:4px 0;">{{ $attendeeName }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Attendee Email:</td>
                                <td style="color:#0f172a;padding:4px 0;"><code>{{ $attendeeEmail }}</code></td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Notification UUID:</td>
                                <td style="color:#0f172a;padding:4px 0;"><code>{{ $notificationUuid }}</code></td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Notification Type:</td>
                                <td style="color:#0f172a;padding:4px 0;">{{ $notificationType }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Payment Status:</td>
                                <td style="color:#0f172a;padding:4px 0;">{{ $paymentStatus }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Payment Amount:</td>
                                <td style="color:#0f172a;padding:4px 0;">CAD {{ $paymentAmount }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Attempts Count:</td>
                                <td style="color:#0f172a;padding:4px 0;">{{ $retryCount }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#475569;vertical-align:top;padding:4px 0;">Failure Timestamp:</td>
                                <td style="color:#0f172a;padding:4px 0;">{{ $failureTimestamp }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;color:#dc2626;vertical-align:top;padding:8px 0 4px;">Error Details:</td>
                                <td style="color:#b91c1c;padding:8px 0 4px;font-family:monospace;word-break:break-all;">{{ $errorMessage }}</td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td align="center" style="padding:10px 0 20px;">
                                    <a href="{{ $adminCommunicationsUrl }}" target="_blank" style="display:inline-block;padding:12px 24px;background:#0f172a;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:6px;box-shadow:0 1px 3px 0 rgba(0,0,0,0.1);">
                                        Open Communications Portal
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0;font-size:13px;line-height:1.5;color:#64748b;">
                            Please review this failure and proceed with a manual retry once any configuration or email service issues are resolved.
                        </p>
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td style="background:#f1f5f9;color:#64748b;padding:16px 24px;font-size:12px;text-align:center;border-top:1px solid #e2e8f0;">
                        SFU MSA Platform Control Plane • Automated Alert Systems
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
