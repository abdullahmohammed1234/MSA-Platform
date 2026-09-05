<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Upcoming Due Date Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #d97706;">SFU MSA Library — Upcoming Due Date Reminder</h2>
    <p>Assalamu Alaikum {{ $loan->member->name }},</p>
    <p>This is a friendly reminder that your borrowed book is due in <strong>2 calendar days</strong>.</p>
    
    <div style="background-color: #fef3c7; border-left: 4px solid #d97706; padding: 15px; margin: 20px 0;">
        <p style="margin: 0 0 8px 0;"><strong>Book Title:</strong> {{ $loan->copy->book->title }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Copy Barcode:</strong> {{ $loan->copy->barcode }}</p>
        <p style="margin: 0;"><strong>Due Date:</strong> <span style="color: #b45309; font-weight: bold;">{{ $loan->due_at->format('F j, Y') }}</span></p>
    </div>

    <p>Please return the item on or before the due date at the library self-service desk, or renew your loan online at <a href="{{ config('app.url') }}/library/my-loans">My Library Portal</a> if eligible.</p>
    <p>JazakAllah Khair,<br><strong>SFU MSA Library Team</strong></p>
</body>
</html>
