<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'SFU MSA' }}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #F4F4F5;
            color: #1F2937;
            margin: 0;
            padding: 0;
            width: 100% !important;
            -webkit-text-size-adjust: none;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .wrapper {
            background-color: #F4F4F5;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .header {
            background-color: #0D9488; /* Deep Teal */
            padding: 30px 20px;
            text-align: center;
            border-bottom: 4px solid #D97706; /* Gold/Amber */
        }
        .header h1 {
            color: #FFFFFF;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 40px 30px;
            line-height: 1.6;
        }
        .content h2 {
            color: #0D9488;
            margin-top: 0;
            font-size: 20px;
            font-weight: 600;
        }
        .footer {
            background-color: #F9FAFB;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #6B7280;
            border-top: 1px solid #E5E7EB;
        }
        .button {
            display: inline-block;
            background-color: #0D9488;
            color: #FFFFFF !important;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .button:hover {
            background-color: #0F766E;
        }
        .badge {
            display: inline-block;
            background-color: #FEF3C7;
            color: #D97706;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .meta-box {
            background-color: #F3F4F6;
            border-left: 4px solid #D97706;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .meta-box p {
            margin: 5px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1>SFU Muslim Students Association</h1>
            </div>

            <!-- Content -->
            <div class="content">
                @yield('content')
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>This is an automated notification from the SFU MSA Platform.</p>
                <p>&copy; {{ date('Y') }} SFU MSA. All rights reserved.</p>
                <p>
                    <a href="{{ url('/settings/notifications') }}" style="color: #0D9488; text-decoration: none;">Manage Notification Preferences</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
