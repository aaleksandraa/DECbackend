<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(to right, #ea580c, #dc2626);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .info-row {
            margin-bottom: 15px;
        }
        .label {
            font-weight: bold;
            color: #6b7280;
        }
        .message-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #ea580c;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin: 0;">Nova poruka sa kontakt forme</h2>
        </div>

        <div class="content">
            <div class="info-row">
                <span class="label">Ime:</span> {{ $name }}
            </div>

            <div class="info-row">
                <span class="label">Email:</span>
                <a href="mailto:{{ $email }}">{{ $email }}</a>
            </div>

            @if(!empty($subject))
            <div class="info-row">
                <span class="label">Tema:</span> {{ $subject }}
            </div>
            @endif

            <div class="message-box">
                <p class="label">Poruka:</p>
                <p style="white-space: pre-wrap;">{{ $message }}</p>
            </div>
        </div>

        <div class="footer">
            <p>Ova poruka je poslana sa kontakt forme na frizerino.com</p>
            <p>Odgovorite direktno na email pošiljaoca: {{ $email }}</p>
        </div>
    </div>
</body>
</html>
