<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Overdue Book Notice</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #dc2626;">SFU MSA Library — OVERDUE BOOK NOTICE</h2>
    <p>Assalamu Alaikum {{ $loan->member->name }},</p>
    <p>According to our library records, the following item is now <strong>OVERDUE</strong>.</p>
    
    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
        <p style="margin: 0 0 8px 0;"><strong>Book Title:</strong> {{ $loan->copy->book->title }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Copy Barcode:</strong> {{ $loan->copy->barcode }}</p>
        <p style="margin: 0;"><strong>Was Due On:</strong> <span style="color: #dc2626; font-weight: bold;">{{ $loan->due_at->format('F j, Y') }}</span></p>
    </div>

    <p style="color: #991b1b; font-weight: bold;">Important: Items overdue by more than 7 days will automatically suspend your borrowing privileges until returned.</p>
    <p>Please return this book as soon as possible at the SFU MSA Library self-service desk.</p>
    <p>JazakAllah Khair,<br><strong>SFU MSA Library Team</strong></p>
</body>
</html>
