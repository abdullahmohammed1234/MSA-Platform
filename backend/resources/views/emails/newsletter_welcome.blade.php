@extends('emails.layout')

@section('content')
    <h2>Welcome to the SFU MSA Newsletter</h2>
    <p>Assalamu Alaikum,</p>
    <p>Thank you for subscribing to the SFU MSA newsletter with <strong>{{ $subscriberEmail }}</strong>.</p>
    <p>You will now receive updates about our latest events, prayer times, and community news whenever we publish new announcements.</p>
    <p>Barakallahu Feekum,<br>SFU MSA Team</p>
@endsection
