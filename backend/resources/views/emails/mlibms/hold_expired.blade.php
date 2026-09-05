<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hold Expiration Notice</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #6b7280;">SFU MSA Library — Hold Reservation Expired</h2>
    <p>Assalamu Alaikum {{ $reservation->member->name }},</p>
    <p>Your hold reservation for <strong>{{ $reservation->book->title }}</strong> has expired as it was not picked up within the 3-day window.</p>
    <p>The copy has been released to the next hold or returned to the general available catalog. You may place another hold reservation anytime on <a href="{{ config('app.url') }}/library">SFU MSA Library</a>.</p>
    <p>JazakAllah Khair,<br><strong>SFU MSA Library Team</strong></p>
</body>
</html>
