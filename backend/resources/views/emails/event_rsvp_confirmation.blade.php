<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event RSVP Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.6;">
    <h2 style="color: #640c0e;">You're registered!</h2>

    <p>Assalamu alaikum {{ $registrantName }},</p>

    <p>Your RSVP for <strong>{{ $event->title }}</strong> has been confirmed.</p>

    <ul>
        <li><strong>Date:</strong> {{ $event->date }}</li>
        <li><strong>Time:</strong> {{ $event->time }}</li>
        <li><strong>Location:</strong> {{ $event->location }}</li>
        <li><strong>Student ID:</strong> {{ $studentId }}</li>
    </ul>

    <p>We look forward to seeing you there. If your plans change, please contact events@sfumsa.ca.</p>

    <p style="color: #666; font-size: 12px;">SFU Muslim Students Association</p>
</body>
</html>
