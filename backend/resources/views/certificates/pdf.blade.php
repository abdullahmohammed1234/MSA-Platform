<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background-color: #FAF9F6;
            color: #1A1A1A;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .cert-container {
            width: 297mm;
            height: 210mm;
            box-sizing: border-box;
            padding: 20mm;
            position: relative;
            background-color: #ffffff;
            border: 15px solid {{ $primaryColor }};
        }
        .cert-inner-border {
            border: 2px solid {{ $secondaryColor }};
            height: 100%;
            width: 100%;
            box-sizing: border-box;
            padding: 15mm;
            position: relative;
        }
        .logo-area {
            margin-bottom: 5mm;
        }
        .institution {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 14pt;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: {{ $primaryColor }};
            margin-bottom: 2mm;
            font-weight: bold;
        }
        .academy {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: {{ $secondaryColor }};
            margin-bottom: 10mm;
        }
        .title {
            font-size: 36pt;
            font-weight: normal;
            color: {{ $primaryColor }};
            margin-bottom: 6mm;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .subtitle {
            font-size: 14pt;
            font-style: italic;
            margin-bottom: 8mm;
            color: #555555;
        }
        .student-name {
            font-size: 32pt;
            font-weight: bold;
            color: {{ $primaryColor }};
            border-bottom: 2px solid {{ $secondaryColor }};
            display: inline-block;
            padding-bottom: 5px;
            margin-bottom: 8mm;
            min-width: 150mm;
        }
        .description {
            font-size: 13pt;
            line-height: 1.6;
            margin: 0 auto 12mm auto;
            max-width: 200mm;
            color: #444444;
        }
        .footer-info {
            position: absolute;
            bottom: 15mm;
            left: 15mm;
            right: 15mm;
            width: calc(100% - 30mm);
        }
        .signature-block {
            display: inline-block;
            width: 30%;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-line {
            border-top: 1px solid #777777;
            margin-top: 15px;
            padding-top: 5px;
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #333333;
        }
        .signature-name {
            font-weight: bold;
        }
        .signature-image {
            height: 40px;
            margin-bottom: -5px;
        }
        .meta-block {
            display: inline-block;
            width: 35%;
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            color: #666666;
            vertical-align: bottom;
            line-height: 1.4;
        }
        .verify-text {
            font-size: 7.5pt;
            color: #888888;
            margin-top: 5px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="cert-container">
        <div class="cert-inner-border">
            <div class="logo-area">
                <div class="institution">SFU Muslim Students Association</div>
                <div class="academy">Dawah Academy</div>
            </div>

            <div class="title">Certificate of Completion</div>
            <div class="subtitle">This credential is proudly presented to</div>

            <div class="student-name">{{ $studentName }}</div>

            <div class="description">
                {{ $description }}
            </div>

            <div class="footer-info">
                <!-- Left Signature -->
                <div class="signature-block" style="float: left;">
                    @if(!empty($signatures[0]['image_path']))
                        <img class="signature-image" src="{{ public_path($signatures[0]['image_path']) }}" alt="Signature 1">
                    @else
                        <div style="height: 40px;"></div>
                    @endif
                    <div class="signature-line">
                        <span class="signature-name">{{ $signatures[0]['name'] }}</span><br>
                        <span>{{ $signatures[0]['title'] }}</span>
                    </div>
                </div>

                <!-- Right Signature -->
                <div class="signature-block" style="float: right;">
                    @if(!empty($signatures[1]['image_path']))
                        <img class="signature-image" src="{{ public_path($signatures[1]['image_path']) }}" alt="Signature 2">
                    @else
                        <div style="height: 40px;"></div>
                    @endif
                    <div class="signature-line">
                        <span class="signature-name">{{ $signatures[1]['name'] }}</span><br>
                        <span>{{ $signatures[1]['title'] }}</span>
                    </div>
                </div>

                <!-- Center Meta/Verification info -->
                <div class="meta-block" style="margin: 0 auto; display: block;">
                    <div>Date Issued: {{ $date }}</div>
                    <div>Credential ID: {{ $code }}</div>
                    <div class="verify-text">
                        Verify authenticity at:<br>
                        {{ $verifyUrl }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
