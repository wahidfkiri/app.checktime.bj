<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export des Présences - Pointages</title>
    <style>
        @page {
            margin: 20px;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4e73df;
            padding-bottom: 10px;
        }
        
        .header h1 {
            color: #4e73df;
            font-size: 22px;
            margin: 0 0 5px 0;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 12px;
        }
        
        .header .period {
            color: #4e73df;
            font-size: 13px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .client-info {
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #4e73df;
        }
        
        .client-info h3 {
            color: #4e73df;
            margin: 0 0 8px 0;
            font-size: 14px;
        }
        
        .client-info p {
            margin: 2px 0;
        }
        
        .filters-section {
            margin-bottom: 15px;
        }
        
        .filters-title {
            color: #666;
            font-size: 12px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 8px;
        }
        
        .filter-item {
            background-color: #e9ecef;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 10px;
        }
        
        .filter-label {
            color: #4e73df;
            font-weight: bold;
        }
        
        .statistics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin: 15px 0;
        }
        
        .stat-card {
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            background-color: #f8f9fa;
            border: 1px solid #e3e6f0;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 10px;
        }
        
        table th {
            background-color: #4e73df;
            color: white;
            text-align: left;
            padding: 8px 10px;
            font-weight: bold;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 9px;
            font-weight: bold;
        }
        
        .status-present { background-color: #198754; color: white; }
        .status-absent { background-color: #dc3545; color: white; }
        .status-late { background-color: #ffc107; color: #212529; }
        .status-early_leave { background-color: #0dcaf0; color: #212529; }
        .status-half_day { background-color: #0d6efd; color: white; }
        .status-overtime { background-color: #198754; color: white; }
        .status-short_work { background-color: #ffc107; color: #212529; }
        .status-leave { background-color: #6c757d; color: white; }
        
        .punches-cell {
            max-width: 150px;
            word-wrap: break-word;
        }
        
        .punch-time {
            display: inline-block;
            background-color: #e9ecef;
            padding: 1px 5px;
            margin: 1px 2px;
            border-radius: 3px;
            font-size: 9px;
        }
        
        .employee-not-found {
            color: #dc3545;
            font-style: italic;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e3e6f0;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .page-number {
            position: fixed;
            bottom: 15px;
            right: 15px;
            font-size: 10px;
            color: #666;
        }
        
        /* Pour éviter les coupures de page dans les lignes */
        tr { 
            page-break-inside: avoid; 
        }
        
        /* Styles spécifiques pour l'export */
        .column-date { width: 10%; }
        .column-employee { width: 18%; }
        .column-empcode { width: 8%; }
        .column-punches { width: 22%; }
        .column-checkin { width: 8%; }
        .column-checkout { width: 8%; }
        .column-hours { width: 8%; }
        .column-status { width: 10%; }
        .column-matched { width: 8%; }
        
        .summary-row {
            background-color: #e3f2fd !important;
            font-weight: bold;
        }
        
        .summary-label {
            text-align: right;
            padding-right: 10px !important;
        }
        
        .summary-value {
            color: #4e73df;
        }
        
        .notes-cell {
            font-size: 9px;
            color: #666;
            max-width: 200px;
            word-wrap: break-word;
        }
        
        .hours-detail {
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport des Présences - Pointages</h1>
        <div class="subtitle">Généré le {{ $export_date }}</div>
        <div class="period">Période: {{ $start_date }} au {{ $end_date }}</div>
    </div>
    
    <!-- Informations client -->
    <div class="client-info">
        <h3>Client: {{ $client->nraison_sociale }}</h3>
        <p><strong>Date d'export:</strong> {{ $export_date }}</p>
        <p><strong>Période:</strong> {{ $start_date }} au {{ $end_date }}</p>
        @if(!empty($filters))
        <div class="filters-section">
            <div class="filters-title">Filtres appliqués:</div>
            <div class="filters-grid">
                @foreach($filters as $key => $value)
                <div class="filter-item">
                    <span class="filter-label">{{ $key }}:</span> {{ $value }}
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    
    <!-- Statistiques -->
    @php
        // Calculer les totaux pour le PDF
        $totalHours = collect($attendances)->sum('work_hours');
        $uniqueEmployees = collect($attendances)->pluck('emp_code')->unique()->count();
    @endphp
    
    <div class="statistics">
        <div class="stat-card">
            <div class="stat-number">{{ count($attendances) }}</div>
            <div class="stat-label">Total présences</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $statistics['present'] ?? $statistics['present_days'] ?? 0 }}</div>
            <div class="stat-label">Présents</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $statistics['absent'] ?? $statistics['absent_days'] ?? 0 }}</div>
            <div class="stat-label">Absents</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ number_format($statistics['avg_work_hours'] ?? 0, 2) }}h</div>
            <div class="stat-label">Moyenne heures</div>
        </div>
    </div>
    
    @if(count($attendances) > 0)
    <table>
        <thead>
            <tr>
                <th class="column-date">Date</th>
                <th class="column-employee">Employé</th>
                <th class="column-empcode">Code</th>
                <th class="column-punches">Pointages</th>
                <th class="column-checkin">Check-in</th>
                <th class="column-checkout">Check-out</th>
                <th class="column-hours">Durée</th>
                <th class="column-status">Statut</th>
            </tr>
        </thead>
        <tbody>
            @php
                $currentDate = null;
                $dateCount = 0;
                $pdfTotalHours = 0;
                $pdfUniqueEmployees = [];
            @endphp
            
            @foreach($attendances as $attendance)
                @if($currentDate !== $attendance['date'])
                    @if($currentDate !== null)
                        <!-- Ligne de résumé pour la date précédente -->
                        <tr class="summary-row">
                            <td colspan="3" class="summary-label">
                                Total pour {{ $currentDate }}:
                            </td>
                            <td class="summary-value" colspan="2">
                                {{ $dateCount }} employé(s)
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    @endif
                    
                    @php
                        $currentDate = $attendance['date'];
                        $dateCount = 1;
                    @endphp
                @else
                    @php $dateCount++; @endphp
                @endif
                
                @php
                    $pdfTotalHours += $attendance['work_hours'];
                    if (!in_array($attendance['emp_code'], $pdfUniqueEmployees)) {
                        $pdfUniqueEmployees[] = $attendance['emp_code'];
                    }
                @endphp
            
            <tr>
                <td>{{ $attendance['date'] }}</td>
                <td>
                    {{ $attendance['full_name'] }}
                    @if(strpos($attendance['full_name'], 'Non enregistré') !== false)
                    <span class="employee-not-found">*</span>
                    @endif
                </td>
                <td>{{ $attendance['emp_code'] }}</td>
                <td class="punches-cell">
                    @if(!empty($attendance['punch_times']))
                        @php
                            $punchArray = explode(', ', $attendance['punch_times']);
                        @endphp
                        @foreach($punchArray as $punch)
                            <span class="punch-time">{{ $punch }}</span>
                        @endforeach
                        <div class="hours-detail">
                            Total: {{ count($punchArray) }} pointage(s)
                        </div>
                    @else
                        <span class="text-muted">Aucun</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($attendance['check_in'] !== 'N/A')
                    <strong>{{ $attendance['check_in'] }}</strong>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($attendance['check_out'] !== 'N/A')
                    <strong>{{ $attendance['check_out'] }}</strong>
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($attendance['work_hours'] > 0)
                    <strong>{{ number_format($attendance['work_hours'], 2) }}h</strong>
                    @if(isset($attendance['overtime_hours']) && $attendance['overtime_hours'] > 0)
                        <div class="hours-detail" style="color: #198754;">
                            +{{ number_format($attendance['overtime_hours'], 2) }}h sup
                        </div>
                    @endif
                    @else
                    <span class="text-muted">-</span>
                    @endif
                </td>
                <td class="text-center">
                    <span class="status-badge status-{{ strtolower(str_replace(' ', '_', $attendance['status'])) }}">
                        {{ $attendance['status'] }}
                    </span>
                    @if(!empty($attendance['notes']))
                    <div class="notes-cell">
                        {{ $attendance['notes'] }}
                    </div>
                    @endif
                </td>
            </tr>
            @endforeach
            
            <!-- Dernière ligne de résumé -->
            @if($currentDate !== null)
            <tr class="summary-row">
                <td colspan="3" class="summary-label">
                    Total pour {{ $currentDate }}:
                </td>
                <td class="summary-value" colspan="2">
                    {{ $dateCount }} employé(s)
                </td>
                <td colspan="4"></td>
            </tr>
            @endif
            
            <!-- Ligne de total général -->
            <tr class="summary-row">
                <td colspan="3" class="summary-label">
                    <strong>TOTAL GÉNÉRAL:</strong>
                </td>
                <td class="summary-value">
                    <strong>{{ count($attendances) }} présence(s)</strong>
                </td>
                <td class="text-center">
                    <strong>{{ count($pdfUniqueEmployees) }} employé(s)</strong>
                </td>
                <td class="text-center">
                    <strong>{{ number_format($pdfTotalHours, 2) }}h</strong>
                </td>
                <td class="text-center">
                    <strong>{{ number_format($statistics['total_overtime_hours'] ?? 0, 2) }}h sup</strong>
                </td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
    
    @if(collect($attendances)->contains(function($attendance) {
        return strpos($attendance['full_name'], 'Non enregistré') !== false;
    }))
    <div style="margin-top: 10px; font-size: 10px; color: #dc3545;">
        * Employé non enregistré dans la base de données
    </div>
    @endif
    
    <div class="footer">
        <p>Document généré automatiquement par le système de pointage</p>
        <p><strong>Statistiques:</strong> 
           Présences: {{ count($attendances) }} | 
           Employés uniques: {{ count($pdfUniqueEmployees) }} | 
           Heures totales: {{ number_format($pdfTotalHours, 2) }}h | 
           Heures supplémentaires: {{ number_format($statistics['total_overtime_hours'] ?? 0, 2) }}h</p>
        <p>Présents: {{ $statistics['present'] ?? $statistics['present_days'] ?? 0 }} | 
           Absents: {{ $statistics['absent'] ?? $statistics['absent_days'] ?? 0 }} | 
           Retards: {{ $statistics['late_days'] ?? 0 }} | 
           Demi-journées: {{ $statistics['half_days'] ?? 0 }}</p>
    </div>
    @else
    <div class="no-data">
        <h3>Aucune présence trouvée</h3>
        <p>Aucune donnée ne correspond aux critères de recherche spécifiés.</p>
    </div>
    @endif
    
    <div class="page-number">
        Page 1/1
    </div>
</body>
</html>