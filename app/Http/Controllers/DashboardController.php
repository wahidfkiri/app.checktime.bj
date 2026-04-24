<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Zone;
use App\Models\Device;
use App\Models\DailyAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        // Récupérer le client de l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->first();
        
        // Si l'utilisateur n'a pas de client associé
        if (!$client) {
            return view('dashboard')->with('error', 'Aucun client associé à votre compte.');
        }
        
        $clientId = $client->id;
        $today = Carbon::today();
        
        // SYNC DES APPAREILS AVANT DE CALCULER LES STATS
        $this->syncDevicesIfNeeded($clientId);
        
        // Statistiques Principales pour le client
        $totalEmployees = Employee::where('client_id', $clientId)->count();
        $activeEmployees = Employee::where('client_id', $clientId)->where('status', 'active')->count();
        $inactiveEmployees = Employee::where('client_id', $clientId)->where('status', 'inactive')->count();
        $suspendedEmployees = Employee::where('client_id', $clientId)->where('status', 'suspended')->count();
        
        // Statistiques de présence du jour
        $totalPresentToday = DailyAttendance::where('client_id', $clientId)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('check_in')
            ->count();
        
        $totalAbsentToday = $activeEmployees - $totalPresentToday;
        
        $totalRetardToday = DailyAttendance::where('client_id', $clientId)
            ->whereDate('attendance_date', $today)
            ->where('is_late', true)
            ->count();
        
        $totalDepartments = Department::where('client_id', $clientId)->count();
        $totalZones = Zone::where('client_id', $clientId)->count();
        $totalDevices = Device::where('client_id', $clientId)->count();
        
        // Appareils récemment synchronisés
        $recentlySyncedDevices = Device::where('client_id', $clientId)
            ->whereNotNull('last_sync')
            ->where('last_sync', '>=', Carbon::now()->subDay())
            ->count();
        
        // Données pour graphiques
        $employeeStatusData = [
            'active' => $activeEmployees,
            'inactive' => $inactiveEmployees,
            'suspended' => $suspendedEmployees
        ];
        
        // Statistiques de présence pour le graphique
        $attendanceTodayData = [
            'present' => $totalPresentToday,
            'absent' => $totalAbsentToday,
            'retard' => $totalRetardToday
        ];
        
        // Top départements par nombre d'employés
        $topDepartments = Employee::where('client_id', $clientId)
            ->select('dept_name', DB::raw('COUNT(*) as count'))
            ->whereNotNull('dept_name')
            ->groupBy('dept_name')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();
        
        $topDepartmentsLabels = $topDepartments->pluck('dept_name')->toArray();
        $topDepartmentsCountData = $topDepartments->pluck('count')->toArray();
        
        // Top zones par nombre d'employés
        $topZones = Employee::where('client_id', $clientId)
            ->select('area_name', DB::raw('COUNT(*) as count'))
            ->whereNotNull('area_name')
            ->groupBy('area_name')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();
        
        $topZonesLabels = $topZones->pluck('area_name')->toArray();
        $topZonesCountData = $topZones->pluck('count')->toArray();
        
        // Croissance mensuelle des employés
        $monthlyStats = $this->getMonthlyStats($clientId);
        $monthlyLabels = $monthlyStats['labels'];
        $monthlyNewEmployees = $monthlyStats['new_employees'];
        
        // Statistiques de présence hebdomadaires
        $weeklyAttendance = $this->getWeeklyAttendanceStats($clientId);
        
        // Derniers employés ajoutés
        $recentEmployees = Employee::where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        // Dernières présences enregistrées
        $recentAttendances = DailyAttendance::where('client_id', $clientId)
            ->with('employee')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        // Statut des appareils (calculé APRÈS synchronisation)
        $fifteenDaysAgo = Carbon::now()->subDays(15);
        
        $activeDevices = Device::where('client_id', $clientId)
            ->whereNotNull('last_sync')
            ->where('last_sync', '>=', $fifteenDaysAgo)
            ->count();
        
        $inactiveDevices = Device::where('client_id', $clientId)
            ->where(function($query) use ($fifteenDaysAgo) {
                $query->whereNull('last_sync')
                      ->orWhere('last_sync', '<', $fifteenDaysAgo);
            })
            ->count();
        
        // Calcul des pourcentages
        $activeEmployeesPercentage = $totalEmployees > 0 ? round(($activeEmployees / $totalEmployees) * 100) : 0;
        $activeDevicesPercentage = $totalDevices > 0 ? round(($activeDevices / $totalDevices) * 100) : 0;
        $attendanceRate = $activeEmployees > 0 ? round(($totalPresentToday / $activeEmployees) * 100) : 0;
        
        // Synchronisation status
        $lastSyncTime = Cache::get('employees_last_sync_' . $clientId);
        $lastSyncText = $lastSyncTime ? Carbon::createFromTimestamp($lastSyncTime)->diffForHumans() : 'Jamais';
        
        // Dernière synchro des appareils
        $lastDevicesSync = Cache::get('devices_last_sync_' . $clientId);
        $lastDevicesSyncText = $lastDevicesSync ? Carbon::createFromTimestamp($lastDevicesSync)->diffForHumans() : 'Jamais';
        
        return view('dashboard', compact(
            'client',
            'totalEmployees',
            'activeEmployees',
            'inactiveEmployees',
            'suspendedEmployees',
            'totalPresentToday',
            'totalAbsentToday',
            'totalRetardToday',
            'attendanceRate',
            'totalDepartments',
            'totalZones',
            'totalDevices',
            'recentlySyncedDevices',
            'employeeStatusData',
            'attendanceTodayData',
            'topDepartmentsLabels',
            'topDepartmentsCountData',
            'topZonesLabels',
            'topZonesCountData',
            'monthlyLabels',
            'monthlyNewEmployees',
            'weeklyAttendance',
            'recentEmployees',
            'recentAttendances',
            'activeDevices',
            'inactiveDevices',
            'activeEmployeesPercentage',
            'activeDevicesPercentage',
            'lastSyncText',
            'lastDevicesSyncText'
        ));
    }

    /**
     * Synchronise les appareils si nécessaire avant d'afficher les stats
     */
    private function syncDevicesIfNeeded(int $clientId): void
    {
        // Ne pas synchroniser si déjà en cours
        if (Cache::get('devices_syncing_' . $clientId, false)) {
            return;
        }
        
        $lastSync = Cache::get('devices_last_sync_' . $clientId, 0);
        $syncInterval = 300; // 5 minutes
        
        // Si jamais synchronisé ou si ça fait plus de X secondes
        if ($lastSync == 0 || (time() - $lastSync) > $syncInterval) {
            // Lancer la synchronisation
            $this->syncDevicesForClient($clientId);
        }
    }

    /**
     * Synchronise les appareils pour le client spécifié
     */
    private function syncDevicesForClient(int $clientId): void
    {
        try {
            // Marquer comme en cours de synchronisation
            Cache::put('devices_syncing_' . $clientId, true, 300);
            
            Log::info("Dashboard - Synchronisation des devices pour le client {$clientId}");
            
            // Récupérer la configuration d'accès du client
            $accessConfig = DB::table('access_configs')->where('client_id', $clientId)->first();
            
            if (!$accessConfig || empty($accessConfig->general_token)) {
                Log::warning("Dashboard - Aucune configuration d'accès trouvée pour le client {$clientId}");
                Cache::forget('devices_syncing_' . $clientId);
                return;
            }
            
            $token = $accessConfig->general_token;
            
            // Récupérer toutes les devices de l'API
            $allDevices = $this->fetchAllDevicesFromAPI($token);
            
            if (empty($allDevices)) {
                Cache::forget('devices_syncing_' . $clientId);
                return;
            }
            
            // Synchroniser chaque device
            $syncedCount = 0;
            foreach ($allDevices as $deviceData) {
                if ($this->syncSingleDevice($deviceData, $clientId)) {
                    $syncedCount++;
                }
            }
            
            // Supprimer les devices qui n'existent plus dans l'API
            $this->deleteMissingDevices($allDevices, $clientId);
            
            // Mettre à jour le cache
            Cache::put('devices_last_sync_' . $clientId, time(), now()->addHours(2));
            
            Log::info("Dashboard - Synchronisation terminée pour le client {$clientId}: {$syncedCount} devices");
            
        } catch (\Exception $e) {
            Log::error("Dashboard - Erreur syncDevicesForClient client {$clientId}: " . $e->getMessage());
        } finally {
            Cache::forget('devices_syncing_' . $clientId);
        }
    }

    /**
     * Récupère TOUTES les devices depuis l'API (avec pagination)
     */
    private function fetchAllDevicesFromAPI(string $token): array
    {
        $allDevices = [];
        $page = 1;
        $hasMore = true;
        
        try {
            while ($hasMore && $page <= 20) { // Limite de sécurité
                $response = Http::withHeaders([
                    "Authorization" => "Token " . $token,
                    "Accept" => "application/json"
                ])
                ->timeout(30)
                ->get('http://54.37.15.111/iclock/api/terminals/', [
                    'page' => $page,
                    'limit' => 100
                ]);
                
                if (!$response->successful()) {
                    Log::warning("Dashboard - Échec de récupération des devices - Page {$page}");
                    break;
                }
                
                $data = $response->json();
                
                if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
                    break;
                }
                
                // Ajouter les devices de cette page
                $allDevices = array_merge($allDevices, $data['data']);
                
                // Vérifier s'il y a une page suivante
                $hasMore = isset($data['next']) && !empty($data['next']);
                $page++;
                
                // Petite pause pour éviter de surcharger l'API
                if ($hasMore) {
                    usleep(200000); // 0.2 seconde
                }
            }
            
            Log::info("Dashboard - Récupéré " . count($allDevices) . " devices depuis l'API");
            
        } catch (\Exception $e) {
            Log::error('Dashboard - Erreur fetchAllDevicesFromAPI: ' . $e->getMessage());
        }
        
        return $allDevices;
    }

    /**
     * Synchronise une seule device
     */
    private function syncSingleDevice(array $deviceData, int $clientId): bool
    {
        try {
            // Vérifier les données minimales
            if (empty($deviceData['sn']) || empty($deviceData['alias'])) {
                return false;
            }
            
            $deviceCode = $deviceData['sn'];
            
            // Vérifier si la device existe déjà
            $existingDevice = Device::where('device_sn', $deviceCode)
                               ->where('client_id', $clientId)
                               ->first();
            
            // Préparer les données
            $deviceAttributes = [
                'alias' => $deviceData['alias'],
                'ip' => $deviceData['ip_address'] ?? null,
                'terminal_name' => $deviceData['terminal_name'] ?? null,
                'area_name' => $deviceData['area_name'] ?? null,
                'last_sync' => $deviceData['last_activity'] ?? null,
                'metadata' => json_encode($deviceData),
                'updated_at' => now(),
            ];
            
            if ($existingDevice) {
                // Mettre à jour la device existante
                $existingDevice->update($deviceAttributes);
            } else {
                // Créer une nouvelle device
                $deviceAttributes['device_sn'] = $deviceCode;
                $deviceAttributes['client_id'] = $clientId;
                $deviceAttributes['created_at'] = now();
                
                Device::create($deviceAttributes);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Dashboard - Erreur syncSingleDevice {$deviceData['sn']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprime les devices qui n'existent plus dans l'API
     */
    private function deleteMissingDevices(array $apiDevices, int $clientId): void
    {
        try {
            // Extraire les codes de device de l'API
            $apiDeviceCodes = [];
            foreach ($apiDevices as $device) {
                if (!empty($device['sn'])) {
                    $apiDeviceCodes[] = $device['sn'];
                }
            }
            
            if (empty($apiDeviceCodes)) {
                return;
            }
            
            // Trouver les devices locales qui ne sont plus dans l'API
            $devicesToDelete = Device::where('client_id', $clientId)
                                ->whereNotIn('device_sn', $apiDeviceCodes)
                                ->get();
            
            // Supprimer les devices obsolètes
            foreach ($devicesToDelete as $device) {
                $device->delete();
                Log::info("Dashboard - Device supprimée: {$device->device_sn} (client {$clientId}) - n'existe plus dans l'API");
            }
            
        } catch (\Exception $e) {
            Log::error("Dashboard - Erreur deleteMissingDevices client {$clientId}: " . $e->getMessage());
        }
    }

    private function getMonthlyStats($clientId, $months = 6)
    {
        $labels = [];
        $newEmployees = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();
            
            $labels[] = $date->format('M Y');
            
            // Nouveaux employés ce mois-ci
            $newEmployees[] = Employee::where('client_id', $clientId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
        }

        return [
            'labels' => $labels,
            'new_employees' => $newEmployees
        ];
    }

    private function getWeeklyAttendanceStats($clientId)
    {
        $stats = [];
        $startOfWeek = Carbon::now()->startOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            
            $present = DailyAttendance::where('client_id', $clientId)
                ->whereDate('attendance_date', $date)
                ->whereNotNull('check_in')
                ->count();
            
            $retard = DailyAttendance::where('client_id', $clientId)
                ->whereDate('attendance_date', $date)
                ->where('is_late', true)
                ->count();

            $absent = DailyAttendance::where('client_id', $clientId)
                ->whereDate('attendance_date', $date)
                ->whereNull('check_in')
                ->count();
            
            $stats[] = [
                'day' => $date->format('D'),
                'date' => $date->format('Y-m-d'),
                'present' => $present,
                'absent' => $absent,
                'retard' => $retard
            ];
        }
        
        return $stats;
    }

    public function getStatsJson()
    {
        // Récupérer le client de l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->first();
        
        if (!$client) {
            return response()->json([
                'error' => 'Aucun client associé à votre compte.'
            ], 404);
        }
        
        $clientId = $client->id;
        $today = Carbon::today();
        
        // SYNC DES APPAREILS AVANT DE RETOURNER LES STATS JSON
        $this->syncDevicesIfNeeded($clientId);
        
        $activeEmployees = Employee::where('client_id', $clientId)->where('status', 'active')->count();
        $totalPresentToday = DailyAttendance::where('client_id', $clientId)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('check_in')
            ->count();
        
        // Calcul des appareils actifs/inactifs
        $fifteenDaysAgo = Carbon::now()->subDays(15);
        
        $activeDevices = Device::where('client_id', $clientId)
            ->whereNotNull('last_sync')
            ->where('last_sync', '>=', $fifteenDaysAgo)
            ->count();
        
        $inactiveDevices = Device::where('client_id', $clientId)
            ->where(function($query) use ($fifteenDaysAgo) {
                $query->whereNull('last_sync')
                      ->orWhere('last_sync', '<', $fifteenDaysAgo);
            })
            ->count();
        
        return response()->json([
            'totalEmployees' => Employee::where('client_id', $clientId)->count(),
            'activeEmployees' => $activeEmployees,
            'totalPresentToday' => $totalPresentToday,
            'totalAbsentToday' => $activeEmployees - $totalPresentToday,
            'totalRetardToday' => DailyAttendance::where('client_id', $clientId)
                ->whereDate('attendance_date', $today)
                ->where('is_late', true)
                ->count(),
            'totalDepartments' => Department::where('client_id', $clientId)->count(),
            'totalZones' => Zone::where('client_id', $clientId)->count(),
            'totalDevices' => Device::where('client_id', $clientId)->count(),
            'activeDevices' => $activeDevices,
            'inactiveDevices' => $inactiveDevices,
            'recentlySyncedDevices' => Device::where('client_id', $clientId)
                ->whereNotNull('last_sync')
                ->where('last_sync', '>=', Carbon::now()->subDay())
                ->count(),
            'attendanceRate' => $activeEmployees > 0 ? round(($totalPresentToday / $activeEmployees) * 100, 2) : 0,
            'lastDevicesSync' => Cache::get('devices_last_sync_' . $clientId) ? 
                date('d/m/Y H:i:s', Cache::get('devices_last_sync_' . $clientId)) : 'Jamais'
        ]);
    }

    public function getClientDetails($clientId)
    {
        // Vérifier que l'utilisateur a accès à ce client
        $client = Client::where('id', $clientId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $today = Carbon::today();
        
        // SYNC DES APPAREILS AVANT DE RETOURNER LES DÉTAILS
        $this->syncDevicesIfNeeded($clientId);
        
        $activeEmployees = Employee::where('client_id', $clientId)->where('status', 'active')->count();
        $totalPresentToday = DailyAttendance::where('client_id', $clientId)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('check_in')
            ->count();
        
        // Calcul des appareils actifs/inactifs
        $fifteenDaysAgo = Carbon::now()->subDays(15);
        
        $activeDevices = Device::where('client_id', $clientId)
            ->whereNotNull('last_sync')
            ->where('last_sync', '>=', $fifteenDaysAgo)
            ->count();
        
        $inactiveDevices = Device::where('client_id', $clientId)
            ->where(function($query) use ($fifteenDaysAgo) {
                $query->whereNull('last_sync')
                      ->orWhere('last_sync', '<', $fifteenDaysAgo);
            })
            ->count();

        $details = [
            'client' => $client,
            'stats' => [
                'employees' => Employee::where('client_id', $clientId)->count(),
                'zones' => Zone::where('client_id', $clientId)->count(),
                'departments' => Department::where('client_id', $clientId)->count(),
                'devices' => Device::where('client_id', $clientId)->count(),
                'active_devices' => $activeDevices,
                'inactive_devices' => $inactiveDevices,
                'active_employees' => $activeEmployees,
                'inactive_employees' => Employee::where('client_id', $clientId)->where('status', 'inactive')->count(),
                'present_today' => $totalPresentToday,
                'absent_today' => $activeEmployees - $totalPresentToday,
                'retard_today' => DailyAttendance::where('client_id', $clientId)
                    ->whereDate('attendance_date', $today)
                    ->where('is_late', true)
                    ->count(),
                'created_at' => $client->created_at->format('d/m/Y H:i'),
                'is_active' => $client->is_active,
                'last_devices_sync' => Cache::get('devices_last_sync_' . $clientId) ? 
                    date('d/m/Y H:i:s', Cache::get('devices_last_sync_' . $clientId)) : 'Jamais'
            ]
        ];

        return response()->json($details);
    }
}