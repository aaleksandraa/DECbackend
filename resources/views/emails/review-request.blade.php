<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ocijenite Vaše iskustvo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
        }
        .title {
            font-size: 24px;
            color: #1f2937;
            margin: 20px 0 10px;
        }
        .subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        .content {
            margin: 30px 0;
        }
        .greeting {
            font-size: 18px;
            color: #374151;
        }
        .details-box {
            background-color: #f3f4f6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-row:last-child {
            border-bottom: none;
        }
        .details-label {
            color: #6b7280;
            font-weight: 500;
        }
        .details-value {
            color: #111827;
            font-weight: 600;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
        }
        .stars {
            font-size: 32px;
            margin: 20px 0;
            color: #fbbf24;
        }
        .message {
            color: #4b5563;
            font-size: 15px;
            line-height: 1.7;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 13px;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">💇 Frizerski Saloni</div>
            <h1 class="title">Termin uspješno završen!</h1>
            <p class="subtitle">Hvala što ste nas posjetili</p>
        </div>

        <div class="content">
            <p class="greeting">Poštovani/a {{ $clientName }},</p>

            <p class="message">
                Nadamo se da ste zadovoljni uslugom u salonu <strong>{{ $salonName }}</strong>.
                Vaše mišljenje nam je izuzetno važno i pomaže drugim korisnicima da pronađu pravi salon.
            </p>

            <div class="details-box">
                <div class="details-row">
                    <span class="details-label">Salon:</span>
                    <span class="details-value">{{ $salonName }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Usluga:</span>
                    <span class="details-value">{{ $serviceName }}</span>
                </div>
                <div class="details-row">
                    <span class="details-label">Frizer:</span>
                    <span class="details-value">{{ $staffName }}</span>
                </div>
            </div>

            <div class="cta-section">
                <div class="stars">★ ★ ★ ★ ★</div>
                <p class="message" style="margin-bottom: 20px;">
                    Molimo Vas da ostavite recenziju i podijelite svoje iskustvo sa drugima.
                </p>
                <a href="{{ $reviewUrl }}" class="cta-button">
                    ⭐ Ostavite recenziju
                </a>
            </div>

            <p class="message" style="margin-top: 30px;">
                Hvala Vam što koristite našu platformu!<br>
                Radujemo se Vašem sljedećem posjetu.
            </p>
        </div>

        <div class="footer">
            <p>
                Ovaj email je poslan automatski sa platforme Frizerski Saloni.<br>
                Ako ne želite primati ovakve emailove, možete se odjaviti u postavkama profila.
            </p>
            <p style="margin-top: 10px;">
                © {{ date('Y') }} Frizerski Saloni. Sva prava zadržana.
            </p>
        </div>
    </div>
</body>
</html>
