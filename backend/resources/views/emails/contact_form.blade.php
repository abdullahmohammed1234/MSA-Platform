@extends('emails.layout')

@section('content')
    <h2>New Contact Form Message</h2>
    <p>A visitor submitted the SFU MSA website contact form.</p>

    <div class="meta-box">
        <p><strong>Name:</strong> {{ $senderName }}</p>
        <p><strong>Email:</strong> <a href="mailto:{{ $senderEmail }}">{{ $senderEmail }}</a></p>
        <p><strong>Subject:</strong> {{ $subjectLine }}</p>
    </div>

    <h3 style="color: #0D9488; font-size: 16px; margin-bottom: 10px;">Message</h3>
    <p style="white-space: pre-wrap; margin: 0;">{{ $messageBody }}</p>

    <p style="margin-top: 30px; font-size: 14px; color: #6B7280;">
        Reply directly to this email to respond to {{ $senderName }}.
    </p>
@endsection
