<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novi termin zakazan - Frizerino</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 24px; font-weight: 600; margin: 0;">
                                🎉 Novi termin zakazan!
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Greeting -->
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 24px;">
                                Pozdrav,
                            </p>
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 32px;">
                                @if($recipientType === 'staff')
                                Imate novi termin zakazan:
                                @else
                                Novi termin je zakazan u vašem salonu:
                                @endif
                            </p>

                            <!-- Details Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f0fdf4; border-radius: 8px; border: 2px solid #10b981; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <!-- Client -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #d1fae5; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #065f46; font-size: 14px; vertical-align: top; font-weight: 600;">Klijent</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">
                                                    {{ $appointment->client_name }}
                                                    @if($appointment->is_guest)
                                                    <span style="background-color: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; margin-left: 8px;">Gost</span>
                                                    @endif
                                                    <br>
                                                    <span style="font-weight: 400; color: #065f46; font-size: 14px;">
                                                        📞 {{ $appointment->client_phone }}
                                                    </span>
                                                    @if($appointment->client_email)
                                                    <br>
                                                    <span style="font-weight: 400; color: #065f46; font-size: 14px;">
                                                        ✉️ {{ $appointment->client_email }}
                                                    </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Service(s) -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #d1fae5; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #065f46; font-size: 14px; vertical-align: top; font-weight: 600;">
                                                    @if($appointment->isMultiService())
                                                    Usluge
                                                    @else
                                                    Usluga
                                                    @endif
                                                </td>
                                                <td style="color: #111827; font-size: 15px;">
                                                    @if($appointment->isMultiService())
                                                        @foreach($appointment->services() as $service)
                                                        <div style="margin-bottom: 8px;">
                                                            <span style="font-weight: 600;">{{ $service->name }}</span>
                                                            @if($service->duration > 0)
                                                            <span style="color: #6b7280; font-size: 13px;">({{ $service->duration }} min)</span>
                                                            @endif
                                                            @if($service->price)
                                                            <span style="color: #10b981; margin-left: 8px;">{{ number_format($service->discount_price ?? $service->price, 2) }} KM</span>
                                                            @endif
                                                        </div>
                                                        @endforeach
                                                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #d1fae5;">
                                                            <span style="font-weight: 600; color: #065f46;">Ukupno:</span>
                                                            <span style="color: #10b981; font-weight: 600; margin-left: 8px;">{{ number_format($totalPrice, 2) }} KM</span>
                                                        </div>
                                                    @else
                                                        <span style="font-weight: 600;">{{ $appointment->service->name }}</span>
                                                        @if($appointment->total_price)
                                                        <span style="color: #10b981; margin-left: 8px;">{{ number_format($appointment->total_price, 2) }} KM</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Date & Time -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #d1fae5; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #065f46; font-size: 14px; vertical-align: top; font-weight: 600;">Datum</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedDate }}</td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; @if($recipientType === 'salon' && $appointment->staff) margin-bottom: 16px; border-bottom: 1px solid #d1fae5; padding-bottom: 16px; @endif">
                                            <tr>
                                                <td style="width: 100px; color: #065f46; font-size: 14px; vertical-align: top; font-weight: 600;">Vrijeme</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedTime }} - {{ $endTime }} ({{ $totalDuration }} min)</td>
                                            </tr>
                                        </table>

                                        <!-- Staff (for salon owner) -->
                                        @if($recipientType === 'salon' && $appointment->staff)
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; @if($appointment->notes) margin-bottom: 16px; border-bottom: 1px solid #d1fae5; padding-bottom: 16px; @endif">
                                            <tr>
                                                <td style="width: 100px; color: #065f46; font-size: 14px; vertical-align: top; font-weight: 600;">Frizer</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $appointment->staff->name }}</td>
                                            </tr>
                                        </table>
                                        @endif

                                        <!-- Notes -->
                                        @if($appointment->notes)
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            <tr>
                                                <td style="width: 100px; color: #065f46; font-size: 14px; vertical-align: top; font-weight: 600;">Napomena</td>
                                                <td style="color: #111827; font-size: 14px; line-height: 1.5;">{{ $appointment->notes }}</td>
                                            </tr>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <!-- Booking Source -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #eff6ff; border-radius: 6px; border: 1px solid #93c5fd; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="color: #1e40af; font-size: 14px; margin: 0; line-height: 1.5;">
                                            <strong>Izvor rezervacije:</strong>
                                            @if($appointment->booking_source === 'widget')
                                            Widget (zakazano sa web stranice)
                                            @elseif($appointment->booking_source === 'guest')
                                            Ručno dodato
                                            @else
                                            Frizerino platforma
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 24px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.frontend_url', 'https://frizerino.com') }}/dashboard?section=calendar&date={{ \Carbon\Carbon::parse($appointment->date)->format('Y-m-d') }}&appointment={{ $appointment->id }}" style="display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-size: 15px; font-weight: 600;">
                                            Pregledaj u kalendaru
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Footer Note -->
                            <p style="color: #6b7280; font-size: 13px; text-align: center; margin: 0; line-height: 1.5;">
                                Možete potvrditi ili otkazati termin direktno iz kalendara.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 8px;">Frizerino</p>
                            <p style="color: #9ca3af; font-size: 13px; margin: 0 0 12px;">
                                Sistem za upravljanje salonima
                            </p>
                            <p style="margin: 0;">
                                <a href="https://frizerino.com" style="color: #10b981; text-decoration: none; font-size: 13px;">frizerino.com</a>
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
