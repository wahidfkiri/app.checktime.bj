@extends('layouts.app')

@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Historique des pointages</h3>
                        <!-- <p class="text-subtitle text-muted">Données chargées directement depuis l'API</p> -->
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('home') }}">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item active">Pointage</li>
                            </ol>
                        </nav>
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
                                        <h6 class="card-title">Filtres de consultation</h6>
                                        <div class="row g-3">
                                            <!-- Date début -->
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label for="filter_start_date" class="form-label">Date début</label>
                                                    <input type="date" class="form-control" id="filter_start_date" 
                                                           value="{{ date('Y-m-d') }}">
                                                </div>
                                            </div>
                                            
                                            <!-- Date fin -->
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label for="filter_end_date" class="form-label">Date fin</label>
                                                    <input type="date" class="form-control" id="filter_end_date" 
                                                           value="{{ date('Y-m-d') }}">
                                                </div>
                                            </div>
                                            
                                            <!-- Terminal -->
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label for="filter_terminal_sn" class="form-label">Terminal</label>
                                                    <select class="form-control" id="filter_terminal_sn">
                                                        <option value="all">Tous les terminaux</option>
                                                        @foreach($devices as $device)
                                                            <option value="{{ $device->device_sn }}">
                                                                {{ $device->terminal_name ?: $device->device_sn }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Employé -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="filter_emp_code" class="form-label">Employé</label>
                                                    <select class="form-control" id="filter_emp_code">
                                                        <option value="all">Tous les employés</option>
                                                        @foreach($employees as $employee)
                                                            <option value="{{ $employee['emp_code'] }}">
                                                                {{ $employee['emp_code'] }} - {{ $employee['full_name'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Boutons -->
                                            <div class="col-md-3">
                                                <div class="form-group text-start">
                                                    <label class="form-label d-block" style="margin-bottom:0px;">&nbsp;</label>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-primary" id="apply_filters">
                                                            <i class="bi bi-funnel me-1"></i> Appliquer
                                                        </button>
                                                        <button type="button" class="btn btn-outline-primary ms-2" id="today_button">
                                                            <i class="bi bi-calendar-check me-1"></i> Aujourd'hui
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Info -->
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="alert alert-info alert-sm p-2 mb-0">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                  Les données sont chargées directement depuis le Terminal Biométrique. Par défaut: données d'aujourd'hui.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Résultat chargement initial -->
                        <div id="initial-loading-result" class="mb-3">
                            @if($todayData['success'])
                                <div class="alert alert-success">
                                    <h6>✅ Données chargées avec succès</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <p><strong>Date:</strong> {{ $todayData['date'] }}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Pointages:</strong> {{ $todayData['total_attendances'] }}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Employés identifiés:</strong> {{ $todayData['matched_employees'] }}</p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Terminaux:</strong> {{ $devices->count() }}</p>
                                        </div>
                                    </div>
                                    
                                    @if(isset($todayData['unmatched_employees']) && $todayData['unmatched_employees'] > 0)
                                        <div class="alert alert-warning mt-2 p-2">
                                            <p class="mb-1">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                <strong>Attention:</strong> {{ $todayData['unmatched_employees'] }} employé(s) non trouvé(s) dans la base
                                            </p>
                                            @if(isset($todayData['unmatched_codes']) && count($todayData['unmatched_codes']) > 0)
                                                <p class="mb-0">
                                                    <small>Codes manquants: 
                                                        @if(count($todayData['unmatched_codes']) <= 10)
                                                            {{ implode(', ', $todayData['unmatched_codes']) }}
                                                        @else
                                                            {{ implode(', ', array_slice($todayData['unmatched_codes'], 0, 10)) }} et {{ count($todayData['unmatched_codes']) - 10 }} autres...
                                                        @endif
                                                    </small>
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-warning">
                                    <h6>⚠️ Aucune donnée trouvée pour aujourd'hui</h6>
                                    <p><strong>Message:</strong> {{ $todayData['message'] }}</p>
                                    <p class="mb-0">Utilisez les filtres ci-dessus pour rechercher d'autres dates ou terminaux.</p>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Alerte chargement -->
                        <div class="alert alert-info alert-dismissible fade show d-none" id="loading-alert" role="alert">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <strong id="loading-message">Chargement des données ...</strong>
                            </div>
                        </div>
                        
                        <!-- Tableau -->
                        <div class="table-responsive">
                            <table id="attendances-table" class="table table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Code</th>
                                        <th>Employé</th>
                                        <th>Dates du pointage</th>
                                        <!-- <th>Départ</th> -->
                                        <th>Durée</th>
                                        <!-- <th>Terminal</th> -->
                                        <th>Statut</th>
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
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

<script>
$(document).ready(function() {
    // ========== FONCTIONS UTILITAIRES ==========
    
    // Obtenir la date d'aujourd'hui
    function getTodayDate() {
        var today = new Date();
        var year = today.getFullYear();
        var month = String(today.getMonth() + 1).padStart(2, '0');
        var day = String(today.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    // Afficher le chargement
    function showLoading(message) {
        $('#loading-message').text(message || 'Chargement...');
        $('#loading-alert').removeClass('d-none');
    }
    
    // Masquer le chargement
    function hideLoading() {
        $('#loading-alert').addClass('d-none');
    }
    
    // Mettre à jour l'heure de la dernière requête
    function updateLastRequestTime() {
        var now = new Date();
        var timeString = now.toLocaleTimeString('fr-FR', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        $('#last-request').text('Dernière requête: ' + timeString);
    }
    
    // Afficher une notification
    function showSweetAlert(icon, title, text, timer = null) {
        Swal.fire({
            icon: icon,
            title: title,
            html: text,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: timer || 5000,
            timerProgressBar: true
        });
    }
    
    // ========== INITIALISATION ==========
    
    // Obtenir et afficher la date d'aujourd'hui
    var todayDate = getTodayDate();
    $('#filter_start_date').val(todayDate);
    $('#filter_end_date').val(todayDate);
    
    console.log("Initialisation avec date:", todayDate);
    
    // ========== DATATABLE CONFIGURATION ==========
    
    var table = $('#attendances-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('daily-attendance.data') }}",
            type: 'GET',
            data: function(d) {
                // Récupérer tous les filtres
                var startDate = $('#filter_start_date').val();
                var endDate = $('#filter_end_date').val();
                var terminalSn = $('#filter_terminal_sn').val();
                var empCode = $('#filter_emp_code').val();
                
                // Si les dates sont vides, utiliser aujourd'hui
                if (!startDate || startDate.trim() === '') {
                    startDate = todayDate;
                    $('#filter_start_date').val(todayDate);
                }
                if (!endDate || endDate.trim() === '') {
                    endDate = todayDate;
                    $('#filter_end_date').val(todayDate);
                }
                
                // Envoyer les filtres au serveur
                d.start_date = startDate;
                d.end_date = endDate;
                d.terminal_sn = terminalSn;
                d.emp_code = empCode;
                
                console.log("Filtres envoyés:", {
                    start_date: startDate,
                    end_date: endDate,
                    terminal_sn: terminalSn,
                    emp_code: empCode
                });
            },
            beforeSend: function() {
                showLoading('Chargement des données ...');
            },
            complete: function() {
                hideLoading();
                updateLastRequestTime();
            },
            error: function(xhr, error, thrown) {
                hideLoading();
                console.error('Erreur DataTables:', xhr.responseJSON);
                
                var errorMessage = 'Une erreur est survenue lors du chargement des données';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                }
                
                showSweetAlert('error', 'Erreur', errorMessage);
            }
        },
        columns: [
            { 
                data: 'date',
                name: 'date',
                width: '10%',
                render: function(data) {
                    if (!data) return '-';
                    try {
                        return new Date(data).toLocaleDateString('fr-FR');
                    } catch (e) {
                        return data;
                    }
                }
            },
            { 
                data: 'emp_code',
                name: 'emp_code',
                width: '8%',
                render: function(data) {
                    return data || '-';
                }
            },
            { 
                data: 'employee.full_name',
                name: 'employee.full_name',
                width: '15%',
                render: function(data, type, row) {
                    if (!data || data === 'Non enregistré' || data === 'Non enregistré') {
                        return '<span class="text-warning" title="Employé non trouvé dans la base de données"><i class="bi bi-exclamation-triangle me-1"></i>Non enregistré</span>';
                    }
                    return data;
                }
            },
            { 
                data: null,
                name: 'attendance_times',
                width: '15%',
                render: function(data, type, row) {
                    var arrival = row.arrival_time || '';
                    var departure = row.departure_time || '';
                    return arrival + ' <br> ' + departure;
                }
            },
            { 
        data: 'total_work_hours', // Changé de total_work_minutes
        name: 'total_work_hours',
        width: '10%',
        render: function(data) {
            if (!data && data !== 0) return '-';
            // Afficher en heures avec le format "X.XX h"
            return data + ' h';
        }
    },
            // { 
            //     data: 'observation',
            //     name: 'observation',
            //     width: '12%',
            //     render: function(data) {
            //         return data || '-';
            //     }
            // },
            { 
                data: 'status',
                name: 'status',
                width: '12%',
                render: function(data) {
                    var badgeClass = 'secondary';
                    var text = data;
                    
                    switch(data) {
                        case 'present':
                            badgeClass = 'success';
                            text = 'Présent';
                            break;
                        case 'absent':
                            badgeClass = 'danger';
                            text = 'Absent';
                            break;
                        case 'late':
                            badgeClass = 'warning';
                            text = 'En retard';
                            break;
                        case 'early_leave':
                            badgeClass = 'info';
                            text = 'Départ anticipé';
                            break;
                        default:
                            badgeClass = 'secondary';
                            text = data || 'N/A';
                    }
                    
                    return '<span class="badge bg-' + badgeClass + '">' + text + '</span>';
                }
            }
        ],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tous"]],
        order: [[0, 'desc']],
        responsive: true,
        drawCallback: function(settings) {
            updateLastRequestTime();
            
            // Mettre en évidence les lignes avec employés non trouvés
            $('span.text-warning').closest('tr').addClass('table-warning');
            
            // Afficher le nombre de résultats
            var api = this.api();
            var pageInfo = api.page.info();
            console.log('Affichage ' + pageInfo.recordsDisplay + ' enregistrements sur ' + pageInfo.recordsTotal);
        },
        initComplete: function() {
            console.log('DataTable initialisé avec succès');
            
            // Masquer le message initial après 3 secondes
            setTimeout(function() {
                $('#initial-loading-result').fadeOut('slow', function() {
                    $(this).addClass('d-none');
                });
            }, 3000);
        }
    });
    
    // ========== GESTION DES FILTRES ==========
    
    // Appliquer les filtres
    $('#apply_filters').on('click', function() {
        var startDate = $('#filter_start_date').val();
        var endDate = $('#filter_end_date').val();
        
        // Validation des dates
        if (!startDate || !endDate) {
            showSweetAlert('error', 'Erreur', 'Veuillez sélectionner une période valide.');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            showSweetAlert('error', 'Erreur', 'La date de début ne peut pas être après la date de fin.');
            return;
        }
        
        // Recharger le tableau avec les nouveaux filtres
        table.ajax.reload();
    });
    
    // Appliquer automatiquement les filtres quand on change les valeurs
    $('#filter_start_date, #filter_end_date, #filter_terminal_sn, #filter_emp_code').on('change', function() {
        $('#apply_filters').click();
    });
    
    // ========== BOUTON AUJOURD'HUI ==========
    
    $('#today_button').on('click', function() {
        // Réinitialiser tous les filtres
        $('#filter_start_date').val(todayDate);
        $('#filter_end_date').val(todayDate);
        $('#filter_terminal_sn').val('all');
        $('#filter_emp_code').val('all');
        
        // Recharger le tableau
        table.ajax.reload();
        
        // Notification
        showSweetAlert('success', 'Succès', 'Filtres réinitialisés à aujourd\'hui', 2000);
    });
    
    // ========== STYLES DYNAMIQUES ==========
    
    // Ajouter les styles CSS dynamiquement
    var dynamicStyles = `
        /* Animation de rotation */
        .spin { 
            animation: spin 1s linear infinite; 
        }
        
        @keyframes spin { 
            100% { 
                transform: rotate(360deg); 
            } 
        }
        
        /* Style pour les lignes avec avertissement */
        .table-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }
        
        .table-warning:hover {
            background-color: rgba(255, 193, 7, 0.2) !important;
        }
        
        /* Style pour le texte d'avertissement */
        .text-warning {
            color: #ffc107 !important;
            font-weight: 500;
        }
        
        /* Style pour les badges */
        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
                gap: 5px;
            }
            
            #today_button {
                margin-left: 0 !important;
                margin-top: 5px;
            }
            
            .dataTables_wrapper {
                font-size: 0.9rem;
            }
        }
    `;
    
    // Injecter les styles dans le head
    $('<style>').text(dynamicStyles).appendTo('head');
    
    // ========== LOG DE DÉMARRAGE ==========
    console.log('Application de pointage initialisée');
    console.log('URL API:', '{{ route("daily-attendance.data") }}');
    console.log('Date initiale:', todayDate);
});
</script>

<style>
    /* Styles complémentaires */
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    
    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    
    #loading-alert {
        margin-bottom: 1rem;
    }
    
    .alert-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .text-subtitle {
        font-size: 0.875rem;
        color: #6c757d;
    }
    
    #initial-loading-result {
        transition: opacity 0.5s ease;
    }
    
    select.form-control option {
        padding: 8px;
    }
    
    .form-label {
        font-weight: 500;
        margin-bottom: 0.3rem;
    }
    
    .btn-group .btn {
        border-radius: 0.375rem !important;
    }
</style>
@endsection