<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podsjetnik za termin - Frizerino</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%); padding: 32px 40px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 12px;">⏰</div>
                            <h1 style="color: #ffffff; font-size: 24px; font-weight: 600; margin: 0;">
                                Podsjetnik za termin
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Greeting -->
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 24px;">
                                Pozdrav{{ $appointment->client ? ' ' . explode(' ', $appointment->client->name)[0] : ($appointment->client_name ? ' ' . explode(' ', $appointment->client_name)[0] : '') }},
                            </p>
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 32px;">
                                Ovo je podsjetnik da imate zakazan termin <strong>{{ $hoursUntil }}</strong>:
                            </p>

                            <!-- Details Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #eff6ff; border-radius: 8px; border: 2px solid #3b82f6; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <!-- Salon -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #bfdbfe; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #1e40af; font-size: 14px; vertical-align: top; font-weight: 600;">Salon</td>
                                                <td style="color: #1e3a8a; font-size: 16px; font-weight: 700;">
                                                    {{ $appointment->salon->name }}<br>
                                                    <span style="font-weight: 400; color: #3b82f6; font-size: 14px;">{{ $appointment->salon->address }}, {{ $appointment->salon->city }}</span>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Service -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #bfdbfe; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #1e40af; font-size: 14px; vertical-align: top; font-weight: 600;">Usluga</td>
                                                <td style="color: #1e3a8a; font-size: 16px; font-weight: 700;">
                                                    {{ $appointment->service->name }}
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Date & Time -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #bfdbfe; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #1e40af; font-size: 14px; vertical-align: top; font-weight: 600;">Datum</td>
                                                <td style="color: #1e3a8a; font-size: 16px; font-weight: 700;">{{ $formattedDate }}</td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; @if($appointment->staff) margin-bottom: 16px; border-bottom: 1px solid #bfdbfe; padding-bottom: 16px; @endif">
                                            <tr>
                                                <td style="width: 100px; color: #1e40af; font-size: 14px; vertical-align: top; font-weight: 600;">Vrijeme</td>
                                                <td style="color: #1e3a8a; font-size: 18px; font-weight: 700;">{{ $formattedTime }} - {{ $endTime }}</td>
                                            </tr>
                                        </table>

                                        <!-- Staff (if assigned) -->
                                        @if($appointment->staff)
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            <tr>
                                                <td style="width: 100px; color: #1e40af; font-size: 14px; vertical-align: top; font-weight: 600;">Frizer</td>
                                                <td style="color: #1e3a8a; font-size: 16px; font-weight: 700;">{{ $appointment->staff->name }}</td>
                                            </tr>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Note -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #fef3c7; border-radius: 6px; border: 1px solid #fbbf24; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="color: #92400e; font-size: 14px; margin: 0; line-height: 1.5;">
                                            <strong>💡 Savjet:</strong> Preporučujemo da stignete 5 minuta ranije. Ako ne možete doći, molimo vas da otkažete termin što prije.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Contact Info -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f0fdf4; border-radius: 8px; border: 1px solid #86efac; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="color: #166534; font-size: 15px; font-weight: 600; margin: 0 0 12px; text-align: center;">
                                            📞 Kontakt salon
                                        </p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            @if($appointment->salon->phone)
                                            <tr>
                                                <td style="text-align: center; padding: 4px 0;">
                                                    <a href="tel:{{ $appointment->salon->phone }}" style="color: #166534; text-decoration: none; font-weight: 600; font-size: 16px;">
                                                        {{ $appointment->salon->phone }}
                                                    </a>
                                                </td>
                                            </tr>
                                            @endif
                                            @if($appointment->salon->email)
                                            <tr>
                                                <td style="text-align: center; padding: 4px 0;">
                                                    <a href="mailto:{{ $appointment->salon->email }}" style="color: #166534; text-decoration: none; font-weight: 500; font-size: 14px;">
                                                        {{ $appointment->salon->email }}
                                                    </a>
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="text-align: center; padding: 8px 0 0;">
                                                    <span style="color: #166534; font-size: 14px;">
                                                        📍 {{ $appointment->salon->address }}, {{ $appointment->salon->city }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Buttons -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 24px;">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 12px;">
                                        <a href="{{ config('app.frontend_url', 'https://frizerino.com') }}/moji-termini" style="display: inline-block; background-color: #3b82f6; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-size: 15px; font-weight: 600;">
                                            Pregledaj detalje termina
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.frontend_url', 'https://frizerino.com') }}/moji-termini" style="color: #dc2626; text-decoration: none; font-size: 14px; font-weight: 500;">
                                            Otkaži termin
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Thank You -->
                            <p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 24px 0 0; text-align: center;">
                                Vidimo se uskoro! 💇‍♀️✨
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 8px;">Frizerino</p>
                            <p style="color: #9ca3af; font-size: 13px; margin: 0 0 12px;">
                                Pronađite i zakažite termine u najboljim salonima
                            </p>
                            <p style="margin: 0;">
                                <a href="https://frizerino.com" style="color: #3b82f6; text-decoration: none; font-size: 13px;">frizerino.com</a>
                            </p>
                            <p style="color: #9ca3af; font-size: 11px; margin: 16px 0 0;">
                                © {{ date('Y') }} Frizerino. Ovo je automatski podsjetnik.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
