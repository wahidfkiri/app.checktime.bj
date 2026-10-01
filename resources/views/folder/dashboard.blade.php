@extends('layouts.app')
@section('content')
<div id="main" class="layout-navbar navbar-fixed">
    <x-nav-bar />
    <div id="main-content">
        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Tableau de Bord</h3>
                        <p class="text-subtitle text-muted">
                            Vue d'ensemble de votre compte
                            @if(isset($client) && $client)
                                - {{ $client->raison_sociale }}
                            @endif
                        </p>
                    </div>
                    <div class="col-12 col-md-6 order-md-2 order-first">
                        <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Accueil</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Statistiques Principales -->
            <section class="section">
                <div class="row">
                    <!-- Employés Totaux -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon purple mb-2">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Employés</h6>
                                        <h4 class="font-extrabold mb-0">{{ $totalEmployees ?? 0 }}</h4>
                                        <small class="text-success">
                                            <i class="fas fa-user-check"></i> {{ $activeEmployees ?? 0 }} actifs
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Départements -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon green mb-2">
                                            <i class="fas fa-layer-group"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Départements</h6>
                                        <h4 class="font-extrabold mb-0">{{ $totalDepartments ?? 0 }}</h4>
                                        <small class="text-info">
                                            <i class="fas fa-sitemap"></i> Organisation interne
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zones -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon orange mb-2">
                                            <i class="fas fa-map-marked-alt"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Zones</h6>
                                        <h4 class="font-extrabold mb-0">{{ $totalZones ?? 0 }}</h4>
                                        <small class="text-warning">
                                            <i class="fas fa-globe"></i> Zones géographiques
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appareils -->
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon red mb-2">
                                            <i class="fas fa-fingerprint"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Appareils</h6>
                                        <h4 class="font-extrabold mb-0">{{ $totalDevices ?? 0 }}</h4>
                                        <small class="text-secondary">
                                            <i class="fas fa-wifi"></i> Synchronisés: {{ $recentlySyncedDevices ?? 0 }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Graphiques et Visualisations -->
            <section class="section">
                <div class="row">
                    <!-- Statut des Employés -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Statut des Employés</h4>
                            </div>
                            <div class="card-body">
                                <div id="employee-status-chart"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Départements -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Top Départements</h4>
                            </div>
                            <div class="card-body">
                                <div id="departments-chart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau des Derniers Employés -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Derniers Employés Ajoutés</h4>
                                <a href="{{ route('employees.index') }}" class="btn btn-primary btn-sm">Voir tous</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Nom Complet</th>
                                                <th>Zone</th>
                                                <th>Département</th>
                                                <th>Téléphone</th>
                                                <th>Email</th>
                                                <th>Statut</th>
                                                <th>Date Ajout</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentEmployees as $employee)
                                            <tr>
                                                <td>
                                                    <strong>{{ $employee->emp_code ?? 'N/A' }}</strong>
                                                </td>
                                                <td>{{ $employee->first_name ?? '' }} {{ $employee->last_name ?? '' }}</td>
                                                <td>{{ $employee->area_name ?? 'N/A' }}</td>
                                                <td>{{ $employee->dept_name ?? 'N/A' }}</td>
                                                <td>{{ $employee->phone ?? 'N/A' }}</td>
                                                <td>{{ $employee->email ?? 'N/A' }}</td>
                                                <td>
                                                    @php
                                                        $status = strtolower($employee->status ?? 'active');
                                                        $statusClasses = [
                                                            'active' => 'badge bg-success',
                                                            'inactive' => 'badge bg-danger',
                                                            'suspended' => 'badge bg-warning'
                                                        ];
                                                        $statusClass = $statusClasses[$status] ?? 'badge bg-secondary';
                                                    @endphp
                                                    <span class="{{ $statusClass }}">{{ ucfirst($status) }}</span>
                                                </td>
                                                <td>{{ $employee->created_at ? $employee->created_at->format('d/m/Y') : 'N/A' }}</td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="8" class="text-center">Aucun employé trouvé</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiques Détaillées -->
                <div class="row mt-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Statut des Appareils</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon-sm blue me-3">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Appareils Actifs</h6>
                                                <h4 class="font-extrabold mb-0">{{ $activeDevices ?? 0 }}</h4>
                                                <small class="text-muted">Synchronisés récemment</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon-sm red me-3">
                                                <i class="fas fa-times-circle"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Appareils Inactifs</h6>
                                                <h4 class="font-extrabold mb-0">{{ $inactiveDevices ?? 0 }}</h4>
                                                <small class="text-muted">Non synchronisés</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="progress mb-3">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: {{ $activeDevicesPercentage ?? 0 }}%">
                                                {{ $activeDevicesPercentage ?? 0 }}%
                                            </div>
                                        </div>
                                        <small class="text-muted">Taux d'activité des appareils</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Performance Globale</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="progress mb-3">
                                            <div class="progress-bar bg-info" role="progressbar" 
                                                 style="width: {{ $activeEmployeesPercentage ?? 0 }}%">
                                                {{ $activeEmployeesPercentage ?? 0 }}%
                                            </div>
                                        </div>
                                        <small class="text-muted">Taux d'employés actifs</small>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex align-items-center">
                                            <div class="stats-icon-sm orange me-3">
                                                <i class="fas fa-sync-alt"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Dernière Synchronisation</h6>
                                                <p class="font-extrabold mb-0">{{ $lastSyncText ?? 'Jamais' }}</p>
                                                <small class="text-muted">Données mises à jour</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vue d'Ensemble Graphique -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Évolution des Employés</h4>
                            </div>
                            <div class="card-body">
                                <div id="monthly-growth-chart"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stats-icon-sm {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.stats-icon.purple { background-color: #6f42c1; }
.stats-icon.green { background-color: #198754; }
.stats-icon.orange { background-color: #fd7e14; }
.stats-icon.red { background-color: #dc3545; }

.stats-icon-sm.blue { background-color: #0d6efd; }
.stats-icon-sm.red { background-color: #dc3545; }
.stats-icon-sm.orange { background-color: #fd7e14; }

.card {
    transition: transform 0.3s;
    border: 1px solid #e0e0e0;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.progress {
    height: 10px;
    border-radius: 5px;
}

.badge {
    font-size: 0.75em;
    padding: 0.4em 0.8em;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique de statut des employés (camembert)
    var employeeStatusOptions = {
        series: [
            {{ $employeeStatusData['active'] ?? 0 }},
            {{ $employeeStatusData['inactive'] ?? 0 }},
            {{ $employeeStatusData['suspended'] ?? 0 }}
        ],
        chart: {
            type: 'pie',
            height: 350,
        },
        labels: ['Actifs', 'Inactifs', 'Suspendus'],
        colors: ['#198754', '#dc3545', '#ffc107'],
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
                legend: {
                    position: 'bottom'
                }
            }
        }],
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + " employés"
                }
            }
        }
    };

    var employeeStatusChart = new ApexCharts(document.querySelector("#employee-status-chart"), employeeStatusOptions);
    employeeStatusChart.render();

    // Graphique des top départements
    var departmentsOptions = {
        series: [{
            data: @json($topDepartmentsCountData ?? [])
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true,
            }
        },
        dataLabels: {
            enabled: true
        },
        xaxis: {
            categories: @json($topDepartmentsLabels ?? []),
            title: {
                text: "Nombre d'employés"
            }
        },
        colors: ['#0d6efd'],
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + " employés"
                }
            }
        }
    };

    var departmentsChart = new ApexCharts(document.querySelector("#departments-chart"), departmentsOptions);
    departmentsChart.render();

    // Graphique de croissance mensuelle
    var monthlyGrowthOptions = {
        series: [{
            name: "Nouveaux Employés",
            data: @json($monthlyNewEmployees ?? [])
        }],
        chart: {
            height: 350,
            type: 'area',
            toolbar: {
                show: true
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth'
        },
        xaxis: {
            categories: @json($monthlyLabels ?? [])
        },
        yaxis: {
            title: {
                text: 'Nombre'
            }
        },
        colors: ['#20c997'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.3,
                stops: [0, 90, 100]
            }
        }
    };

    var monthlyChart = new ApexCharts(document.querySelector("#monthly-growth-chart"), monthlyGrowthOptions);
    monthlyChart.render();

    // Rafraîchir les statistiques toutes les 5 minutes
    setInterval(function() {
        fetch('/dashboard/stats')
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    updateStats(data);
                }
            })
            .catch(error => console.error('Error:', error));
    }, 300000); // 5 minutes
});

function updateStats(data) {
    // Mettre à jour les compteurs dynamiquement
    const elements = {
        'totalEmployees': data.totalEmployees,
        'activeEmployees': data.activeEmployees,
        'totalDepartments': data.totalDepartments,
        'totalZones': data.totalZones,
        'totalDevices': data.totalDevices,
        'recentlySyncedDevices': data.recentlySyncedDevices
    };

    for (const [key, value] of Object.entries(elements)) {
        const element = document.querySelector(`[data-counter="${key}"]`);
        if (element) {
            animateCounter(element, value);
        }
    }
}

function animateCounter(element, targetValue) {
    let current = parseInt(element.innerText);
    if (isNaN(current)) return;
    
    const increment = targetValue > current ? 1 : -1;
    
    const timer = setInterval(() => {
        current += increment;
        element.innerText = current;
        
        if (current === targetValue) {
            clearInterval(timer);
        }
    }, 10);
}
</script>
@endsection