<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hold Ready for Pickup</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #047857;">SFU MSA Library — Hold Ready for Pickup!</h2>
    <p>Assalamu Alaikum {{ $reservation->member->name }},</p>
    <p>Great news! The book you reserved, <strong>{{ $reservation->book->title }}</strong>, is now ready for pickup at the SFU MSA Library.</p>
    
    <div style="background-color: #ecfdf5; border-left: 4px solid #047857; padding: 15px; margin: 20px 0;">
        <p style="margin: 0 0 8px 0;"><strong>Book Title:</strong> {{ $reservation->book->title }}</p>
        <p style="margin: 0;"><strong>Hold Expires On:</strong> <span style="color: #047857; font-weight: bold;">{{ $reservation->expires_at ? $reservation->expires_at->format('F j, Y') : '3 days' }}</span></p>
    </div>

    <p>Please visit the library and scan the item at the self-service desk to complete your borrowing before the hold expires.</p>
    <p>JazakAllah Khair,<br><strong>SFU MSA Library Team</strong></p>
</body>
</html>
