<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaš salon je odobren!</title>
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #f97316, #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        h1 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: #6b7280;
            text-align: center;
            margin-bottom: 30px;
        }
        .salon-card {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .salon-name {
            font-size: 20px;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 5px;
        }
        .salon-address {
            color: #a16207;
            font-size: 14px;
        }
        .features {
            margin: 30px 0;
        }
        .feature {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .feature-icon {
            width: 24px;
            height: 24px;
            background-color: #dbeafe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .feature-text {
            color: #4b5563;
        }
        .cta-button {
            display: block;
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #f97316, #dc2626);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            margin: 30px 0;
            box-sizing: border-box;
        }
        .cta-button:hover {
            opacity: 0.9;
        }
        .tips {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .tips-title {
            font-weight: bold;
            color: #166534;
            margin-bottom: 10px;
        }
        .tips-list {
            margin: 0;
            padding-left: 20px;
            color: #15803d;
        }
        .tips-list li {
            margin-bottom: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 12px;
        }
        .footer a {
            color: #f97316;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Frizerino</div>
        </div>

        <div class="success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: white;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1>Čestitamo! 🎉</h1>
        <p class="subtitle">Vaš salon je uspješno odobren i sada je vidljiv klijentima.</p>

        <div class="salon-card">
            <div class="salon-name">{{ $salon->name }}</div>
            <div class="salon-address">{{ $salon->address }}, {{ $salon->city }}</div>
        </div>

        <p>Poštovani/a {{ $ownerName }},</p>

        <p>
            Sa zadovoljstvom vas obavještavamo da je vaš salon <strong>{{ $salon->name }}</strong>
            pregledan i odobren od strane našeg tima. Vaš salon je sada vidljiv svim korisnicima
            Frizerino platforme i možete početi primati rezervacije!
        </p>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div class="feature-text">Dodajte usluge i cjenovnik</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div class="feature-text">Dodajte zaposlene i njihove rasporede</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div class="feature-text">Primajte online rezervacije od klijenata</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div class="feature-text">Pratite analitiku i statistike</div>
            </div>
        </div>

        <a href="{{ $dashboardUrl }}" class="cta-button">
            Pristupite vašem Dashboard-u
        </a>

        <div class="tips">
            <div class="tips-title">💡 Savjeti za uspješan početak:</div>
            <ul class="tips-list">
                <li>Dodajte kvalitetne fotografije vašeg salona</li>
                <li>Popunite sve usluge sa cijenama i trajanjem</li>
                <li>Dodajte zaposlene i njihove rasporede rada</li>
                <li>Podijelite link vašeg salona na društvenim mrežama</li>
            </ul>
        </div>

        <p>
            Ako imate bilo kakvih pitanja ili trebate pomoć, slobodno nas kontaktirajte na
            <a href="mailto:podrska@frizerino.com">podrska@frizerino.com</a>.
        </p>

        <p>Srdačan pozdrav,<br>Tim Frizerino</p>

        <div class="footer">
            <p>
                Ovaj email je poslan sa <a href="https://frizerino.com">Frizerino</a> platforme.<br>
                © {{ date('Y') }} Frizerino. Sva prava zadržana.
            </p>
        </div>
    </div>
</body>
</html>
