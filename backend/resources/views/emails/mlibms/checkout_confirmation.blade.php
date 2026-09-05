<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Borrowing Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #047857;">SFU MSA Library — Borrowing Confirmation</h2>
    <p>Assalamu Alaikum {{ $loan->member->name }},</p>
    <p>You have checked out <strong>{{ $loan->copy->book->title }}</strong> from the SFU MSA Library.</p>
    
    <div style="background-color: #f3f4f6; border-left: 4px solid #047857; padding: 15px; margin: 20px 0;">
        <p style="margin: 0 0 8px 0;"><strong>Book Title:</strong> {{ $loan->copy->book->title }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Copy Barcode:</strong> {{ $loan->copy->barcode }}</p>
        <p style="margin: 0 0 8px 0;"><strong>Checkout Date:</strong> {{ $loan->checked_out_at->format('F j, Y') }}</p>
        <p style="margin: 0;"><strong>Due Date:</strong> <span style="color: #dc2626; font-weight: bold;">{{ $loan->due_at->format('F j, Y') }}</span></p>
    </div>

    <p>Please ensure the book is returned on or before the due date. You can view your current loans and renew eligible books by visiting <a href="{{ config('app.url') }}/library/my-loans">My Library Portal</a>.</p>
    <p>JazakAllah Khair,<br><strong>SFU MSA Library Team</strong></p>
</body>
</html>
