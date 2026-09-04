@extends('emails.layout')

@section('content')
    <h2 style="color: #0D9488; margin-top: 0;">Daily Volunteer Application Digest</h2>
    <p>Summary of new volunteer applications received on <strong>{{ $dateStr }}</strong>:</p>

    <p style="font-weight: bold; font-size: 14px;">Total Applications Today: {{ $registrations->count() }}</p>

    <div style="margin-top: 20px;">
        @foreach($registrations as $index => $app)
            <div style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                <h3 style="margin-top: 0; margin-bottom: 8px; color: #1E293B; font-size: 16px;">
                    {{ $index + 1 }}. {{ $app->name }}
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #475569;">
                    <tr>
                        <td style="padding: 4px 0; font-weight: bold; width: 140px;">SFU Email:</td>
                        <td style="padding: 4px 0;"><a href="mailto:{{ $app->email }}">{{ $app->email }}</a></td>
                    </tr>
                    @if($app->phone)
                    <tr>
                        <td style="padding: 4px 0; font-weight: bold;">Phone Number:</td>
                        <td style="padding: 4px 0;">{{ $app->phone }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding: 4px 0; font-weight: bold;">Student #:</td>
                        <td style="padding: 4px 0;">{{ $app->student_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-weight: bold;">Target Department:</td>
                        <td style="padding: 4px 0;">{{ $app->department }}</td>
                    </tr>
                </table>
                <div style="margin-top: 12px; padding-top: 12px; border-t: 1px solid #E2E8F0; font-size: 13px;">
                    <strong>Interests & Motivation:</strong>
                    <p style="margin-top: 4px; margin-bottom: 0; white-space: pre-wrap; color: #334155;">{{ $app->interests }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <p style="margin-top: 30px; font-size: 13px; color: #64748B;">
        Manage and review all applications in the <a href="{{ config('app.url') }}/admin/volunteering-registrars" style="color: #0D9488; font-weight: bold;">SFU MSA Admin Portal</a>.
    </p>
@endsection
