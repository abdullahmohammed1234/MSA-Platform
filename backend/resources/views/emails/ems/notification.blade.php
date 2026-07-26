<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f4ef;font-family:Georgia,'Times New Roman',serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f4ef;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" style="max-width:560px;background:#ffffff;border:1px solid #e8e2d6;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:#0b3d2e;color:#f6f4ef;padding:18px 24px;font-size:14px;letter-spacing:0.04em;text-transform:uppercase;">
                        {{ $fromName }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 24px;font-size:16px;line-height:1.55;">
                        {!! $bodyHtml !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 24px;font-size:12px;color:#6b655c;">
                        You received this email because of your registration with SFU MSA Events.
                        Transactional messages cannot be opted out of.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
