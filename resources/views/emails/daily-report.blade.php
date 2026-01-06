<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dnevni Izvještaj</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1F2937;
            background-color: #F3F4F6;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: #FFFFFF;
            padding: 30px 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header .salon-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .header .date {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px 20px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #E5E7EB;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .metric-card {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .metric-card.large {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            border-color: #BFDBFE;
        }

        .metric-label {
            font-size: 12px;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
        }

        .metric-value.large {
            font-size: 36px;
            color: #2563EB;
        }

        .metric-trend {
            font-size: 12px;
            margin-top: 4px;
        }

        .metric-trend.up {
            color: #10B981;
        }

        .metric-trend.down {
            color: #EF4444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th {
            background-color: #F3F4F6;
            color: #6B7280;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 8px;
            text-align: left;
            border-bottom: 2px solid #E5E7EB;
        }

        td {
            padding: 12px 8px;
            border-bottom: 1px solid #E5E7EB;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .total-row {
            font-weight: 700;
            background-color: #F9FAFB;
        }

        .service-item {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px;
            background-color: #F9FAFB;
            border-radius: 6px;
        }

        .service-rank {
            font-size: 20px;
            font-weight: 700;
            color: #2563EB;
            margin-right: 12px;
            min-width: 30px;
        }

        .service-details {
            flex: 1;
        }

        .service-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .service-stats {
            font-size: 12px;
            color: #6B7280;
        }

        .progress-bar {
            height: 8px;
            background-color: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563EB 0%, #3B82F6 100%);
            transition: width 0.3s ease;
        }

        .capacity-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
        }

        .capacity-item {
            background-color: #F9FAFB;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }

        .capacity-label {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 4px;
        }

        .capacity-value {
            font-size: 20px;
            font-weight: 700;
            color: #1F2937;
        }

        .period-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background-color: #F9FAFB;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .period-name {
            font-weight: 600;
        }

        .period-time {
            font-size: 12px;
            color: #6B7280;
        }

        .period-percentage {
            font-weight: 700;
            color: #2563EB;
        }

        .summary-box {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 1px solid #FCD34D;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-title {
            font-size: 16px;
            font-weight: 700;
            color: #92400E;
            margin-bottom: 12px;
        }

        .summary-text {
            font-size: 14px;
            color: #78350F;
            line-height: 1.6;
        }

        .footer {
            background-color: #F9FAFB;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6B7280;
        }

        .footer a {
            color: #2563EB;
            text-decoration: none;
        }

        @media only screen and (max-width: 600px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }

            .capacity-grid {
                grid-template-columns: 1fr;
            }

            .metric-value {
                font-size: 24px;
            }

            .metric-value.large {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>DNEVNI IZVJEŠTAJ</h1>
            <div class="salon-name">{{ $salon->name }}</div>
            <div class="date">{{ $report['salon']['date'] }}</div>
        </div>

        <div class="content">
            <!-- Overview Section -->
            <div class="section">
                <h2 class="section-title">Pregled Dana</h2>

                <div class="overview-grid">
                    <div class="metric-card large">
                        <div class="metric-label">Ukupan Promet</div>
                        <div class="metric-value large">{{ $report['overview']['total_revenue_formatted'] }}</div>
                        @if($report['overview']['trend'])
                            <div class="metric-trend {{ $report['overview']['trend']['direction'] }}">
                                @if($report['overview']['trend']['direction'] === 'up')
                                    ↑ {{ $report['overview']['trend']['percent'] }}% u odnosu na prosek
                                @elseif($report['overview']['trend']['direction'] === 'down')
                                    ↓ {{ abs($report['overview']['trend']['percent']) }}% u odnosu na prosek
                                @else
                                    = U skladu sa prosekom
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="metric-card">
                        <div class="metric-label">Broj Termina</div>
                        <div class="metric-value">{{ $report['overview']['total_appointments'] }}</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-label">Broj Klijenata</div>
                        <div class="metric-value">{{ $report['overview']['unique_clients'] }}</div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-label">Prosječna Vrijednost</div>
                        <div class="metric-value" style="font-size: 20px;">{{ $report['overview']['average_value_formatted'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Staff Performance Section -->
            @if(isset($report['staff_performance']) && count($report['staff_performance']['staff']) > 0)
            <div class="section">
                <h2 class="section-title">Promet po Radniku</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Radnik</th>
                            <th style="text-align: center;">Termini</th>
                            <th style="text-align: right;">Promet</th>
                            <th style="text-align: right;">Udio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['staff_performance']['staff'] as $staff)
                        <tr>
                            <td>{{ $staff['name'] }}</td>
                            <td style="text-align: center;">{{ $staff['appointments'] }}</td>
                            <td style="text-align: right;">{{ $staff['revenue_formatted'] }}</td>
                            <td style="text-align: right;">{{ $staff['percentage'] }}%</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td>UKUPNO</td>
                            <td style="text-align: center;">{{ $report['staff_performance']['total_appointments'] }}</td>
                            <td style="text-align: right;">{{ number_format($report['staff_performance']['total_revenue'], 2, ',', '.') }} KM</td>
                            <td style="text-align: right;">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif

            <!-- Service Insights Section -->
            @if(isset($report['service_insights']) && count($report['service_insights']['top_services']) > 0)
            <div class="section">
                <h2 class="section-title">Top Usluge Dana</h2>

                @foreach($report['service_insights']['top_services'] as $index => $service)
                <div class="service-item">
                    <div class="service-rank">{{ $index + 1 }}.</div>
                    <div class="service-details">
                        <div class="service-name">{{ $service['name'] }}</div>
                        <div class="service-stats">
                            {{ $service['count'] }} termina • {{ $service['revenue_formatted'] }} • {{ $service['revenue_percentage'] }}%
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: {{ $service['revenue_percentage'] }}%;"></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <!-- Capacity Utilization Section -->
            @if(isset($report['capacity']))
            <div class="section">
                <h2 class="section-title">Iskorištenost Kapaciteta</h2>

                <div class="capacity-grid">
                    <div class="capacity-item">
                        <div class="capacity-label">Dostupni Termini</div>
                        <div class="capacity-value">{{ $report['capacity']['available_slots'] }}</div>
                    </div>
                    <div class="capacity-item">
                        <div class="capacity-label">Zauzeti Termini</div>
                        <div class="capacity-value">{{ $report['capacity']['occupied_slots'] }}</div>
                    </div>
                    <div class="capacity-item">
                        <div class="capacity-label">Slobodni Termini</div>
                        <div class="capacity-value">{{ $report['capacity']['free_slots'] }}</div>
                    </div>
                    <div class="capacity-item">
                        <div class="capacity-label">Popunjenost</div>
                        <div class="capacity-value" style="color: #2563EB;">{{ $report['capacity']['utilization_percentage'] }}%</div>
                    </div>
                </div>

                @if(count($report['capacity']['periods']) > 0)
                <div style="margin-top: 20px;">
                    <div style="font-weight: 600; margin-bottom: 12px; font-size: 14px;">Analiza po Periodu</div>
                    @foreach($report['capacity']['periods'] as $period)
                    <div class="period-item">
                        <div>
                            <div class="period-name">{{ $period['name'] }}</div>
                            <div class="period-time">{{ $period['time_range'] }}</div>
                        </div>
                        <div class="period-percentage">{{ $period['percentage'] }}%</div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            <!-- Cancellations Section -->
            @if(isset($report['cancellations']) && $report['cancellations']['total_count'] > 0)
            <div class="section">
                <h2 class="section-title">Otkazivanja i Nepojavljivanja</h2>

                <div class="capacity-grid">
                    <div class="capacity-item">
                        <div class="capacity-label">Otkazani Termini</div>
                        <div class="capacity-value">{{ $report['cancellations']['cancelled_count'] }}</div>
                    </div>
                    <div class="capacity-item">
                        <div class="capacity-label">No-Show</div>
                        <div class="capacity-value">{{ $report['cancellations']['no_show_count'] }}</div>
                    </div>
                </div>

                @if($report['cancellations']['estimated_loss'] > 0)
                <div style="margin-top: 16px; padding: 12px; background-color: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 6px; text-align: center;">
                    <div style="font-size: 12px; color: #991B1B; margin-bottom: 4px;">Procijenjeni Gubitak</div>
                    <div style="font-size: 20px; font-weight: 700; color: #DC2626;">{{ $report['cancellations']['estimated_loss_formatted'] }}</div>
                </div>
                @endif
            </div>
            @endif

            <!-- Summary Section -->
            @if(isset($report['summary']))
            <div class="summary-box">
                <div class="summary-title">Zaključak Dana</div>
                <div class="summary-text">{{ $report['summary'] }}</div>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Ovaj izvještaj je automatski generisan od strane Frizerino platforme.</p>
            <p style="margin-top: 8px;">
                <a href="{{ config('app.frontend_url') }}/dashboard">Prijavite se na dashboard</a> za detaljnije izvještaje.
            </p>
            <p style="margin-top: 16px; font-size: 11px;">
                © {{ date('Y') }} Frizerino. Sva prava zadržana.
            </p>
        </div>
    </div>
</body>
</html>
