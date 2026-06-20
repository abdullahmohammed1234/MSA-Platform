@extends('emails.layout')

@section('content')
    <h2>Upcoming Training Reminder</h2>
    <p>Assalamu Alaikum {{ $name }},</p>
    <p>This is a reminder for an upcoming training session or workshop:</p>
    
    <div class="meta-box">
        <h3 style="margin: 0 0 10px 0; color: #0D9488; font-size: 18px;">{{ $trainingTitle }}</h3>
        <p><strong>Date & Time:</strong> {{ $trainingDate }}</p>
        <p><strong>Location:</strong> {{ $trainingLocation }}</p>
        @if(!empty($trainingDescription))
            <p style="margin-top: 10px; color: #4B5563; font-size: 14px;">{{ $trainingDescription }}</p>
        @endif
    </div>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ $trainingUrl }}" class="button">View Training Details</a>
    </div>

    <p>Barakallahu Feekum,<br>SFU MSA Team</p>
@endsection
