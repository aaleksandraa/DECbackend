<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin otkazan - Frizerino</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color: #dc2626; padding: 32px 40px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 24px; font-weight: 600; margin: 0;">
                                Termin je otkazan
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Greeting -->
                            @if($recipientType === 'client')
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 24px;">
                                Pozdrav{{ $appointment->client_name ? ' ' . explode(' ', $appointment->client_name)[0] : '' }},
                            </p>
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 32px;">
                                @if($cancelledBy === 'salon')
                                Nažalost, salon je otkazao vaš termin. Ispričavamo se zbog neugodnosti.
                                @else
                                Vaš termin je uspješno otkazan.
                                @endif
                            </p>
                            @else
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 24px;">
                                Pozdrav,
                            </p>
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 32px;">
                                @if($cancelledBy === 'client')
                                Klijent je otkazao sljedeći termin:
                                @else
                                Sljedeći termin je otkazan:
                                @endif
                            </p>
                            @endif

                            <!-- Details Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #fef2f2; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        @if($recipientType === 'salon')
                                        <!-- Client Info for salon -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #fecaca; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Klijent</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">
                                                    {{ $appointment->client_name ?? 'Nepoznat' }}
                                                    @if($appointment->client_phone)
                                                    <br><span style="font-weight: 400; color: #6b7280; font-size: 14px;">{{ $appointment->client_phone }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                        @else
                                        <!-- Salon for client -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #fecaca; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Salon</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $appointment->salon->name }}</td>
                                            </tr>
                                        </table>
                                        @endif

                                        <!-- Service -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #fecaca; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Usluga</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $appointment->service->name }}</td>
                                            </tr>
                                        </table>

                                        <!-- Date -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #fecaca; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Datum</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedDate }}</td>
                                            </tr>
                                        </table>

                                        <!-- Time -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; @if($appointment->staff) margin-bottom: 16px; border-bottom: 1px solid #fecaca; padding-bottom: 16px; @endif">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Vrijeme</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedTime }}</td>
                                            </tr>
                                        </table>

                                        <!-- Staff (if assigned) -->
                                        @if($appointment->staff)
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Frizer</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $appointment->staff->name }}</td>
                                            </tr>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <!-- Contact Info (for client emails) -->
                            @if($recipientType === 'client')
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f0fdf4; border-radius: 8px; border: 1px solid #86efac; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="color: #166534; font-size: 15px; font-weight: 600; margin: 0 0 12px; text-align: center;">
                                            💬 Imate pitanje?
                                        </p>
                                        <p style="color: #15803d; font-size: 14px; margin: 0 0 16px; text-align: center; line-height: 1.5;">
                                            Kontaktirajte salon za više informacija:
                                        </p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            @if($appointment->salon->phone)
                                            <tr>
                                                <td style="text-align: center; padding: 4px 0;">
                                                    <span style="color: #166534; font-size: 14px;">📞 </span>
                                                    <a href="tel:{{ $appointment->salon->phone }}" style="color: #166534; text-decoration: none; font-weight: 500; font-size: 14px;">
                                                        {{ $appointment->salon->phone }}
                                                    </a>
                                                </td>
                                            </tr>
                                            @endif
                                            @if($appointment->salon->email)
                                            <tr>
                                                <td style="text-align: center; padding: 4px 0;">
                                                    <span style="color: #166534; font-size: 14px;">✉️ </span>
                                                    <a href="mailto:{{ $appointment->salon->email }}" style="color: #166534; text-decoration: none; font-weight: 500; font-size: 14px;">
                                                        {{ $appointment->salon->email }}
                                                    </a>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <!-- CTA Button -->
                            @if($recipientType === 'client')
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 24px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.frontend_url', 'https://frizerino.com') }}/salon/{{ $appointment->salon->slug ?? $appointment->salon->id }}" style="display: inline-block; background-color: #f97316; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-size: 15px; font-weight: 600;">
                                            Zakaži novi termin
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @else
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 24px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.frontend_url', 'https://frizerino.com') }}/dashboard?section=calendar" style="display: inline-block; background-color: #f97316; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-size: 15px; font-weight: 600;">
                                            Pogledaj kalendar
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @endif
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
                                <a href="https://frizerino.com" style="color: #f97316; text-decoration: none; font-size: 13px;">frizerino.com</a>
                            </p>
                            <p style="color: #9ca3af; font-size: 11px; margin: 16px 0 0;">
                                © {{ date('Y') }} Frizerino
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
