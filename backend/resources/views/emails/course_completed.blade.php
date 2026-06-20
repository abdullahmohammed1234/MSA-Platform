@extends('emails.layout')

@section('content')
    <h2>Course Completed!</h2>
    <p>Assalamu Alaikum {{ $name }},</p>
    <p>Congratulations! You have successfully completed the course <strong>{{ $courseName }}</strong>.</p>
    
    <div class="meta-box">
        <p><strong>Course:</strong> {{ $courseName }}</p>
        <p><strong>Completion Date:</strong> {{ $completionDate }}</p>
    </div>

    <p>We are very proud of your effort and dedication. Keep up the excellent learning journey!</p>
    
    <p><strong>Next Steps:</strong></p>
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
        <tr>
            <td style="padding: 5px 0;">• Review your course progress in the student dashboard.</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;">• Download your certification (if applicable) under the Certificates tab.</td>
        </tr>
        <tr>
            <td style="padding: 5px 0;">• Enroll in the next level course in the Dawah Academy Catalog.</td>
        </tr>
    </table>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ $dashboardUrl }}" class="button">Go to Academy Dashboard</a>
    </div>

    <p>Barakallahu Feekum,<br>SFU MSA Dawah Academy Team</p>
@endsection
