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
            padding: 30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .message-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            background: #ea580c;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin: 0;">Hvala na poruci!</h2>
        </div>

        <div class="content">
            <p>Poštovani/a <strong>{{ $name }}</strong>,</p>

            <div class="message-box">
                <p style="margin: 0;">✅ Vaša poruka je uspješno primljena!</p>
            </div>

            <p>Hvala što ste nas kontaktirali. Naš tim će pregledati vašu poruku i odgovoriti vam u najkraćem mogućem roku, obično u roku od 24 sata radnim danima.</p>

            <p><strong>Vaša poruka:</strong></p>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <p style="white-space: pre-wrap; margin: 0;">{{ $message }}</p>
            </div>

            <p style="margin-top: 30px;">Ako imate hitno pitanje, možete nas kontaktirati direktno na:</p>
            <p style="margin: 5px 0;">
                📧 Email: <a href="mailto:podrska@frizerino.com">podrska@frizerino.com</a>
            </p>

            <div style="text-align: center;">
                <a href="https://frizerino.com" class="button">Posjetite Frizerino</a>
            </div>
        </div>

        <div class="footer">
            <p><strong>Frizerino</strong> - Online zakazivanje termina za frizere i salone</p>
            <p>www.frizerino.com</p>
        </div>
    </div>
</body>
</html>
