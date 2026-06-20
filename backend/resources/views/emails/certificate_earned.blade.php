@extends('emails.layout')

@section('content')
    <h2>Certificate Awarded!</h2>
    <p>Assalamu Alaikum {{ $name }},</p>
    <p>Congratulations! You have successfully earned the official certification: <strong>{{ $certificateTitle }}</strong>.</p>
    
    <div class="meta-box">
        <p><strong>Certification:</strong> {{ $certificateTitle }}</p>
        <p><strong>Credential Code:</strong> <span class="badge">{{ $certificateCode }}</span></p>
        <p><strong>Date of Issue:</strong> {{ $issueDate }}</p>
    </div>

    <p>Your hard work and dedication have paid off. You can now download, print, or share your certificate using the links below.</p>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <a href="{{ $verifyUrl }}" class="button">Verify & View Credential</a>
    </div>

    <p>Barakallahu Feekum,<br>SFU MSA Dawah Academy Team</p>
@endsection
