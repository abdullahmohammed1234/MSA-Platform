@extends('emails.layout')

@section('content')
    <h2>New Announcement</h2>
    <p>Assalamu Alaikum,</p>
    <p>A new announcement has been published on the SFU MSA website:</p>

    <div class="meta-box">
        <h3 style="margin: 0 0 10px 0; color: #0D9488; font-size: 18px;">{{ $announcementTitle }}</h3>
        <p style="margin: 0; font-size: 14px; color: #4B5563; line-height: 1.5;">{{ $announcementExcerpt }}</p>
    </div>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ $announcementUrl }}" class="button">Read Full Announcement</a>
    </div>

    <p style="font-size: 13px; color: #6B7280;">You are receiving this email because you subscribed to the SFU MSA newsletter.</p>
    <p>Barakallahu Feekum,<br>SFU MSA Team</p>
@endsection
