@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Rapport Absences & Retards</h3>
                        <p class="text-subtitle text-muted">Analyse des présences comparées aux plannings</p>
                    </div>
                </div>
            </div>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <!-- Filtres -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Filtres du rapport</h6>
                                        <!-- Formulaire pour PDF -->
                                        <form id="exportPdfForm" action="{{ route('reports.export.pdf') }}" method="POST" style="display: none;">
                                            @csrf
                                            <input type="hidden" name="start_date" id="pdf_start_date">
                                            <input type="hidden" name="end_date" id="pdf_end_date">
                                            <input type="hidden" name="emp_code" id="pdf_emp_code">
                                        </form>
                                        
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="report_start_date" class="form-label">Date début</label>
                                                    <input type="date" class="form-control" id="report_start_date" 
                                                           value="{{ date('Y-m-d', strtotime('-2 days')) }}">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="report_end_date" class="form-label">Date fin</label>
                                                    <input type="date" class="form-control" id="report_end_date" 
                                                           value="{{ date('Y-m-d') }}">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="report_emp_code" class="form-label">Employé</label>
                                                    <select class="form-control" id="report_emp_code">
                                                        <option value="all">Tous les employés</option>
                                                        @foreach($employees as $employee)
                                                            <option value="{{ $employee['emp_code'] }}">
                                                                {{ $employee['emp_code'] }} - {{ $employee['full_name'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group text-start">
                                                    <label class="form-label d-block" style="margin-bottom:0px;">&nbsp;</label>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <button type="button" class="btn btn-primary" id="generate_report">
                                                            <i class="bi bi-file-earmark-text me-1"></i> Générer
                                                        </button>
                                                        <!-- <button type="button" class="btn btn-success" id="export_excel">
                                                            <i class="bi bi-file-excel me-1"></i> Excel
                                                        </button> -->
                                                        <button type="button" class="btn btn-danger" id="export_pdf">
                                                            <i class="bi bi-file-pdf me-1"></i> PDF
                                                        </button>
                                                        <!-- <button type="button" class="btn btn-info" id="preview_pdf">
                                                            <i class="bi bi-eye me-1"></i> Prévisualiser
                                                        </button> -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="alert alert-info alert-sm p-2 mb-0">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Le rapport compare les pointages réels avec les plannings prévus.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Résumé statistique -->
                        <div id="report-summary" class="row mb-3 d-none">
                            <div class="col-md-12">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h6 class="card-title">📊 Résumé du rapport</h6>
                                        <div class="row text-center">
                                            <div class="col-md-2">
                                                <div class="border rounded p-2">
                                                    <h5 class="mb-0" id="total-days">0</h5>
                                                    <small class="text-muted">Jours analysés</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="border rounded p-2">
                                                    <h5 class="mb-0 text-success" id="present-count">0</h5>
                                                    <small class="text-muted">Présences</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="border rounded p-2">
                                                    <h5 class="mb-0 text-danger" id="absent-count">0</h5>
                                                    <small class="text-muted">Absences</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="border rounded p-2">
                                                    <h5 class="mb-0 text-warning" id="late-count">0</h5>
                                                    <small class="text-muted">Retards</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="border rounded p-2">
                                                    <h5 class="mb-0 text-info" id="leave-count">0</h5>
                                                    <small class="text-muted">Congés</small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="border rounded p-2">
                                                    <h5 class="mb-0 text-secondary" id="permission-count">0</h5>
                                                    <small class="text-muted">Permissions</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chargement -->
                        <div class="alert alert-info alert-dismissible fade show d-none" id="report-loading" role="alert">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <strong id="report-loading-message">Génération du rapport en cours...</strong>
                            </div>
                        </div>
                        
                        <!-- Tableau -->
                        <div class="table-responsive">
                            <table id="report-table" class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Code</th>
                                        <th>Employé</th>
                                        <th>Planning</th>
                                        <th>Arrivée</th>
                                        <th>Départ</th>
                                        <th>Retard</th>
                                        <th>Départ anticipé</th>
                                        <th>Heures</th>
                                        <th>Statut</th>
                                        <th>Observations</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

<script>
$(document).ready(function() {
    // Variables
    var reportTable;
    
    // Fonctions utilitaires
    function showReportLoading(message) {
        $('#report-loading-message').text(message);
        $('#report-loading').removeClass('d-none');
    }
    
    function hideReportLoading() {
        $('#report-loading').addClass('d-none');
    }
    
    function showSweetAlert(icon, title, text, showConfirm = false) {
        if (showConfirm) {
            return Swal.fire({
                icon: icon,
                title: title,
                html: text,
                showCancelButton: true,
                confirmButtonText: 'Oui, continuer',
                cancelButtonText: 'Annuler'
            });
        } else {
            Swal.fire({
                icon: icon,
                title: title,
                html: text,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }
    
    function updateReportSummary(data) {
        var present = 0, absent = 0, late = 0, leave = 0, permission = 0;
        var days = new Set();
        
        data.forEach(function(row) {
            days.add(row.date);
            
            switch(row.status) {
                case 'present': present++; break;
                case 'absent': absent++; break;
                case 'leave': leave++; break;
                case 'permission': permission++; break;
            }
            
            if (row.late_minutes > 0) late++;
        });
        
        $('#total-days').text(days.size);
        $('#present-count').text(present);
        $('#absent-count').text(absent);
        $('#late-count').text(late);
        $('#leave-count').text(leave);
        $('#permission-count').text(permission);
        
        $('#report-summary').removeClass('d-none');
    }
    
    // Valider les dates avant export
    function validateDatesForExport() {
        var startDate = $('#report_start_date').val();
        var endDate = $('#report_end_date').val();
        
        if (!startDate || !endDate) {
            showSweetAlert('error', 'Erreur', 'Veuillez sélectionner une période.');
            return false;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            showSweetAlert('error', 'Erreur', 'La date de début ne peut pas être après la date de fin.');
            return false;
        }
        
        var daysDiff = Math.ceil((new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24)) + 1;
        if (daysDiff > 31) {
            showSweetAlert('warning', 'Attention', 
                'La période ne doit pas dépasser 31 jours pour des performances optimales. ' +
                'Période sélectionnée: ' + daysDiff + ' jours.');
            return false;
        }
        
        return true;
    }
    
    // Initialiser DataTable
    function initReportTable() {
        reportTable = $('#report-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('reports.data') }}",
                type: 'GET',
                data: function(d) {
                    d.start_date = $('#report_start_date').val();
                    d.end_date = $('#report_end_date').val();
                    d.emp_code = $('#report_emp_code').val();
                },
                beforeSend: function() {
                    showReportLoading('Analyse des données en cours...');
                },
                complete: function() {
                    hideReportLoading();
                },
                error: function(xhr) {
                    hideReportLoading();
                    var errorMsg = xhr.responseJSON?.error || 'Erreur de génération du rapport';
                    showSweetAlert('error', 'Erreur', errorMsg);
                },
                dataSrc: function(json) {
                    if (json.data && json.data.length > 0) {
                        updateReportSummary(json.data);
                    } else {
                        $('#report-summary').addClass('d-none');
                    }
                    return json.data;
                }
            },
            columns: [
                { 
                    data: 'date',
                    render: function(data) {
                        return data ? new Date(data).toLocaleDateString('fr-FR') : '-';
                    }
                },
                { 
                    data: 'employee_code',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { 
                    data: 'employee_name',
                    render: function(data) {
                        return data || '-';
                    }
                },
                { 
                    data: null,
                    render: function(data) {
                        var scheduleText = '';
                        
                        if (data.schedule_start === '-' && data.schedule_end === '-') {
                            scheduleText = 'Non planifié';
                        } else {
                            scheduleText = data.schedule_start + ' - ' + data.schedule_end;
                            
                            // Ajouter un indicateur si heures manquantes
                            if (data.schedule_start !== '-' && data.schedule_end === '-') {
                                scheduleText += ' <span class="badge bg-warning" title="Heure de fin non définie">!</span>';
                            } else if (data.schedule_start === '-' && data.schedule_end !== '-') {
                                scheduleText += ' <span class="badge bg-warning" title="Heure de début non définie">!</span>';
                            }
                        }
                        
                        return scheduleText;
                    }
                },
                { 
                    data: 'actual_arrival',
                    render: function(data) {
                        return data || '';
                    }
                },
                { 
                    data: 'actual_departure',
                    render: function(data) {
                        return data || '';
                    }
                },
                { 
                    data: 'late_minutes',
                    render: function(data, type, row) {
                        // Si arrivalTime ET departureTime sont null → colonne VIDE
                        if (row.actual_arrival === null && row.actual_departure === null) {
                            return '';
                        }
                        
                        // Si pas de start_time → N/A
                        if (!row.has_start_time) {
                            return '<span class="text-muted" title="Pas d\'heure de début prévue">N/A</span>';
                        }
                        
                        // Si null (pas de calcul possible) → N/A
                        if (data === null) {
                            return '<span class="text-muted" title="Non calculable">N/A</span>';
                        }
                        
                        // Si 0 (pas de retard) → -
                        if (data === 0) {
                            return '-';
                        }
                        
                        // Si nombre > 0 (retard)
                        var lateText = '';
                        if (data < 60) {
                            lateText = data + ' min';
                        } else {
                            var hours = Math.floor(data / 60);
                            var minutes = data % 60;
                            lateText = hours + 'h' + (minutes > 0 ? ' ' + minutes + 'min' : '');
                        }
                        
                        return '<span class="text-danger"><i class="bi bi-clock-history me-1"></i>' + lateText + '</span>';
                    }
                },
                { 
                    data: 'early_leave_minutes',
                    render: function(data, type, row) {
                        // Si arrivalTime ET departureTime sont null → colonne VIDE
                        if (row.actual_arrival === null && row.actual_departure === null) {
                            return '';
                        }
                        
                        // Si pas de départ → N/A
                        if (row.actual_departure === null) {
                            return '<span class="text-muted" title="Pas de départ enregistré">N/A</span>';
                        }
                        
                        // Si pas de end_time → N/A
                        if (!row.has_end_time) {
                            return '<span class="text-muted" title="Pas d\'heure de fin prévue">N/A</span>';
                        }
                        
                        // Si null (pas de calcul possible) → N/A
                        if (data === null) {
                            return '<span class="text-muted" title="Non calculable">N/A</span>';
                        }
                        
                        // Si 0 (pas de départ anticipé) → -
                        if (data === 0) {
                            return '-';
                        }
                        
                        // Si nombre > 0 (départ anticipé)
                        var earlyText = '';
                        if (data < 60) {
                            earlyText = data + ' min';
                        } else {
                            var hours = Math.floor(data / 60);
                            var minutes = data % 60;
                            earlyText = hours + 'h' + (minutes > 0 ? ' ' + minutes + 'min' : '');
                        }
                        
                        return '<span class="text-warning"><i class="bi bi-clock me-1"></i>' + earlyText + '</span>';
                    }
                },
                { 
                    data: 'work_hours',
                    render: function(data) {
                        if (!data || data == 0) return '-';
                        return parseFloat(data).toFixed(2) + ' h';
                    }
                },
                { 
                    data: 'status',
                    render: function(data, type, row) {
                        var badgeClass = 'secondary';
                        var text = data;
                        var tooltip = '';
                        
                        switch(data) {
                            case 'present':
                                badgeClass = 'success';
                                text = 'Présent';
                                if (row.is_late) {
                                    text += ' (Retard)';
                                    tooltip = 'Retard: ' + row.late_minutes + ' min';
                                }
                                break;
                            case 'absent':
                                badgeClass = 'danger';
                                text = 'Absent';
                                if (row.late_minutes !== null && row.late_minutes > 0) {
                                    tooltip = 'Retard calculé: ' + row.late_minutes + ' min';
                                }
                                break;
                            case 'leave':
                                badgeClass = 'info';
                                text = 'Congé';
                                break;
                            case 'permission':
                                badgeClass = 'warning';
                                text = 'Permission';
                                break;
                            case 'weekend':
                                text = 'Weekend';
                                break;
                            case 'holiday':
                                text = 'Férié';
                                break;
                            case 'day_off':
                                text = 'Repos';
                                break;
                            case 'no_schedule':
                                text = 'Non planifié';
                                badgeClass = 'light text-dark';
                                break;
                        }
                        
                        var badge = '<span class="badge bg-' + badgeClass + '"';
                        if (tooltip) {
                            badge += ' title="' + tooltip + '" data-bs-toggle="tooltip"';
                        }
                        badge += '>' + text + '</span>';
                        
                        // Ajouter un indicateur si heures manquantes
                        if (!row.has_start_time || !row.has_end_time) {
                            badge += ' <span class="badge bg-warning" title="Planning incomplet">!</span>';
                        }
                        
                        return badge;
                    }
                },
                { 
                    data: null,
                    render: function(data) {
                        var observations = [];
                        
                        if (data.is_weekend) observations.push('Weekend');
                        if (data.is_holiday) observations.push('Férié');
                        if (data.is_on_leave) observations.push('Congé');
                        if (data.has_permission) observations.push('Permission');
                        if (data.schedule_type === 'planifie') observations.push('Planifié');
                        
                        if (data.all_punches && data.all_punches.length > 2) {
                            observations.push(data.all_punches.length + ' pointages');
                        }
                        
                        return observations.join(', ') || '-';
                    }
                }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            },
            pageLength: 25,
            order: [[0, 'desc']],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="bi bi-file-excel me-1"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Rapport Absences Retards',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="bi bi-printer me-1"></i> Imprimer',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            drawCallback: function() {
                // Initialiser les tooltips Bootstrap
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        });
    }
    
    // Initialisation
    initReportTable();
    
    // Générer le rapport
    $('#generate_report').on('click', function() {
        if (!validateDatesForExport()) return;
        reportTable.ajax.reload();
    });
    
    // Exporter en Excel (via DataTables)
    $('#export_excel').on('click', function() {
        $('.buttons-excel').click();
    });
    
    // Exporter en PDF
    $('#export_pdf').on('click', function() {
        if (!validateDatesForExport()) return;
        
        // Afficher une confirmation avec informations
        var startDate = $('#report_start_date').val();
        var endDate = $('#report_end_date').val();
        var empCode = $('#report_emp_code').val();
        var empName = $('#report_emp_code option:selected').text();
        
        var message = 'Êtes-vous sûr de vouloir exporter en PDF ?<br><br>' +
                     '<strong>Période :</strong> ' + startDate + ' au ' + endDate + '<br>' +
                     '<strong>Employé :</strong> ' + empName;
        
        showSweetAlert('question', 'Exporter en PDF', message, true).then((result) => {
            if (result.isConfirmed) {
                // Remplir le formulaire caché
                $('#pdf_start_date').val(startDate);
                $('#pdf_end_date').val(endDate);
                $('#pdf_emp_code').val(empCode);
                
                // Afficher un loader
                showSweetAlert('info', 'Génération en cours', 'Le PDF est en cours de génération...');
                
                // Soumettre le formulaire
                $('#exportPdfForm').submit();
            }
        });
    });
    
    // Prévisualiser PDF dans un nouvel onglet
    $('#preview_pdf').on('click', function() {
        if (!validateDatesForExport()) return;
        
        var startDate = $('#report_start_date').val();
        var endDate = $('#report_end_date').val();
        var empCode = $('#report_emp_code').val();
        
        // Construire l'URL de prévisualisation
        var url = "{{ route('reports.preview.pdf') }}";
        var params = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            emp_code: empCode,
            _token: "{{ csrf_token() }}"
        });
        
        // Ouvrir dans un nouvel onglet
        window.open(url + '?' + params.toString(), '_blank');
    });
    
    // Appliquer automatiquement quand on change les filtres
    $('#report_start_date, #report_end_date, #report_emp_code').on('change', function() {
        $('#generate_report').click();
    });
    
    // Initialiser les tooltips Bootstrap
    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
});
</script>

<style>
    .card-header { background-color: #f8f9fa; }
    .table th { background-color: #f8f9fa; font-weight: 600; }
    .btn-group .btn { border-radius: 0.375rem !important; }
    
    /* Styles pour les boutons d'export */
    .btn-export {
        min-width: 120px;
    }
    
    @media (max-width: 768px) {
        .d-flex.flex-wrap {
            flex-direction: column;
        }
        .d-flex.flex-wrap .btn {
            width: 100%;
            margin-bottom: 5px;
        }
        .btn-group { flex-direction: column; gap: 5px; }
    }
    
    /* Badges de statut */
    .badge-present { background-color: #198754 !important; }
    .badge-absent { background-color: #dc3545 !important; }
    .badge-late { background-color: #fd7e14 !important; }
    .badge-permission { background-color: #ffc107 !important; color: #000 !important; }
    .badge-leave { background-color: #0dcaf0 !important; color: #000 !important; }
    .badge-weekend { background-color: #6c757d !important; }
    .badge-holiday { background-color: #20c997 !important; }
    
    /* Statistiques */
    #report-summary .border {
        transition: all 0.3s ease;
    }
    #report-summary .border:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    /* Table responsive */
    @media (max-width: 1200px) {
        .table-responsive {
            font-size: 0.9rem;
        }
    }
</style>
@endsection