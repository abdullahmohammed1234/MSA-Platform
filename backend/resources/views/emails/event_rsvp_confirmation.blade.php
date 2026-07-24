<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Registration Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 24px; background: #f7f5f2;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #ece7e0;">
        <h2 style="color: #640c0e; margin-top: 0;">You're registered!</h2>

        <p>Assalamu alaikum {{ $registrantName }},</p>

        <p>Your registration for <strong>{{ $event->title }}</strong> has been confirmed.</p>

        <ul style="padding-left: 18px;">
            <li><strong>Date:</strong> {{ $event->date }}</li>
            <li><strong>Time:</strong> {{ $event->time }}</li>
            <li><strong>Location:</strong> {{ $event->location }}</li>
            <li><strong>Phone:</strong> {{ $registrantPhone }}</li>
            <li><strong>Check-in code:</strong> {{ $checkInCode }}</li>
        </ul>

        <p>Please bring this QR code to the event for identity confirmation and check-in:</p>

        <div style="text-align: center; margin: 28px 0;">
            <img src="{{ $qrDataUri }}" alt="Event check-in QR code" width="220" height="220" style="border: 1px solid #ece7e0; border-radius: 12px; padding: 12px; background: #fff;" />
        </div>

        <p style="color: #555; font-size: 14px;">
            A copy of your QR code is also attached to this email. If your plans change, please contact events@sfumsa.ca.
        </p>

        <p style="color: #666; font-size: 12px; margin-bottom: 0;">SFU Muslim Students Association</p>
    </div>
</body>
</html>
