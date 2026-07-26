@extends('emails.layout')

@section('content')
    <h2>New Volunteer Application</h2>
    <p>A student has submitted the SFU MSA volunteer application form.</p>

    <div class="meta-box">
        <p><strong>Name:</strong> {{ $name }}</p>
        <p><strong>SFU Email:</strong> <a href="mailto:{{ $email }}">{{ $email }}</a></p>
        <p><strong>Student Number:</strong> {{ $studentNumber }}</p>
        <p><strong>Department:</strong> {{ $department }}</p>
    </div>

    <h3 style="color: #0D9488; font-size: 16px; margin-bottom: 10px;">Interests & Motivation</h3>
    <p style="white-space: pre-wrap; margin: 0; margin-bottom: 20px;">{{ $interests }}</p>

    @if($experience)
        <h3 style="color: #0D9488; font-size: 16px; margin-bottom: 10px;">Relevant Experience</h3>
        <p style="white-space: pre-wrap; margin: 0;">{{ $experience }}</p>
    @endif

    <p style="margin-top: 30px; font-size: 14px; color: #6B7280;">
        Reply directly to this email to respond to {{ $name }}.
    </p>
@endsection
