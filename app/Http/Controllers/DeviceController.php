<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CheckTimeService;
use App\Models\Device;
use App\Models\Client;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DeviceController extends Controller
{
    private CheckTimeService $api;

    public function __construct(CheckTimeService $api)
    {
        $this->api = $api;
    }

    public function index(Request $request)
    {
        // Récupérer le client de l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->first();
        
        // Si l'utilisateur n'a pas de client associé
        if (!$client) {
            if ($request->ajax()) {
                return response()->json(['data' => []]);
            }
            return view('devices.index')->with('error', 'Aucun client associé à votre compte.');
        }
        
        // Si c'est une requête AJAX pour DataTables
        if ($request->ajax()) {
            // VÉRIFIER ET SYNCHRONISER avant de retourner les données
            $this->checkAndSyncIfNeeded($client->id);
            return $this->getLocalDevices($request);
        }
        
        // Synchroniser automatiquement au premier chargement si nécessaire
        $this->checkAndSyncIfNeeded($client->id);
        
        return view('devices.index');
    }

    /**
     * Vérifie et synchronise si nécessaire pour un client spécifique
     */
    private function checkAndSyncIfNeeded(int $clientId): bool
    {
        // Ne pas synchroniser si déjà en cours
        if (Cache::get('devices_syncing_' . $clientId, false)) {
            return false;
        }
        
        $lastSync = Cache::get('devices_last_sync_' . $clientId, 0);
        $syncInterval = 300; // 5 minutes pour les tests, ajustez selon vos besoins
        
        // Si jamais synchronisé ou si ça fait plus de X secondes
        if ($lastSync == 0 || (time() - $lastSync) > $syncInterval) {
            // Marquer comme en cours de synchronisation
            Cache::put('devices_syncing_' . $clientId, true, 300);
            
            // Lancer la synchronisation EN TEMPS RÉEL (pas en arrière-plan)
            $this->syncDevicesForClientNow($clientId);
            
            return true;
        }
        
        return false;
    }

    /**
     * Synchronise les devices pour le client spécifié
     */
    private function syncDevicesForClientNow(int $clientId): int
    {
        try {
            Log::info("Début de la synchronisation des devices pour le client {$clientId}");
            
            // Récupérer la configuration d'accès du client
            $accessConfig = DB::table('access_configs')->where('client_id', $clientId)->first();
            
            if (!$accessConfig) {
                Log::warning("Aucune configuration d'accès trouvée pour le client {$clientId}");
                Cache::forget('devices_syncing_' . $clientId);
                return 0;
            }
            
            $token = $accessConfig->general_token;
            
            // Récupérer toutes les devices de l'API
            $allDevices = $this->fetchAllDevicesFromAPI($token);
            
            if (empty($allDevices)) {
                Cache::forget('devices_syncing_' . $clientId);
                return 0;
            }
            
            // Synchroniser chaque device
            $syncedCount = 0;
            foreach ($allDevices as $deviceData) {
                if ($this->syncSingleDevice($deviceData, $clientId)) {
                    $syncedCount++;
                }
            }
            
            // SUPPRIMER les devices qui n'existent plus dans l'API
            $this->deleteMissingDevices($allDevices, $clientId);
            
            // Mettre à jour le cache
            Cache::put('devices_last_sync_' . $clientId, time(), now()->addHours(2));
            Cache::forget('devices_syncing_' . $clientId);
            
            Log::info("Synchronisation terminée pour le client {$clientId}: {$syncedCount} devices");
            
            return $syncedCount;
            
        } catch (\Exception $e) {
            Log::error("Erreur syncDevicesForClientNow client {$clientId}: " . $e->getMessage());
            Cache::forget('devices_syncing_' . $clientId);
            return 0;
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
                    Log::warning("Échec de récupération des devices - Page {$page}");
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
            
            Log::info("Récupéré " . count($allDevices) . " devices depuis l'API");
            
        } catch (\Exception $e) {
            Log::error('Erreur fetchAllDevicesFromAPI: ' . $e->getMessage());
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
                'last_synced_at' => now(),
                'metadata' => json_encode($deviceData),
                'updated_at' => now(),
            ];
            
            if ($existingDevice) {
                // Mettre à jour la device existante
                $existingDevice->update($deviceAttributes);
                Log::debug("Device mise à jour: {$deviceCode} (client {$clientId})");
            } else {
                // Créer une nouvelle device
                $deviceAttributes['device_sn'] = $deviceCode;
                $deviceAttributes['client_id'] = $clientId;
                $deviceAttributes['created_at'] = now();
                
                Device::create($deviceAttributes);
                Log::info("Device créée: {$deviceCode} (client {$clientId})");
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Erreur syncSingleDevice {$deviceData['sn']}: " . $e->getMessage());
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
            $deletedCount = 0;
            foreach ($devicesToDelete as $device) {
                $device->delete();
                $deletedCount++;
                Log::info("Device supprimée: {$device->device_sn} (client {$clientId}) - n'existe plus dans l'API");
            }
            
            if ($deletedCount > 0) {
                Log::info("Supprimé {$deletedCount} devices obsolètes pour le client {$clientId}");
            }
            
        } catch (\Exception $e) {
            Log::error("Erreur deleteMissingDevices client {$clientId}: " . $e->getMessage());
        }
    }

    /**
     * Récupère les données LOCALES pour DataTables
     */
    public function getLocalDevices(Request $request)
    {
        if ($request->ajax()) {
            // Récupérer le client de l'utilisateur connecté
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return DataTables::of([])->make(true);
            }
            
            $query = Device::where('client_id', $client->id);
            
            // Appliquer les filtres
            $this->applyFilters($query, $request);
            
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('code', function($device) {
                    return $device->device_sn ?? 'N/A';
                })
                ->addColumn('alias', function($device) {
                    return $device->alias ?? 'N/A';
                })
                ->addColumn('ip', function($device) {
                    return $device->ip ?? 'N/A';
                })
                ->addColumn('area_name', function($device) {
                    return $device->area_name ?? 'N/A';
                })
                ->addColumn('terminal_name', function($device) {
                    return $device->terminal_name ?? 'N/A';
                })
                ->addColumn('status', function($device) {
                    if (!$device->last_sync) {
                        return '<span class="badge bg-danger-light text-danger">
                            <i class="bi bi-circle-fill me-1 fs-6"></i> Inactif
                        </span>';
                    }

                    if (\Carbon\Carbon::parse($device->last_sync)->diffInDays(now()) <= 15) {
                        return '<span class="badge bg-success-light text-success">
                            <i class="bi bi-check-circle-fill me-1 fs-6"></i> Actif
                        </span>';
                    } else {
                        return '<span class="badge bg-danger-light text-danger">
                            <i class="bi bi-x-circle-fill me-1 fs-6"></i> Inactif
                        </span>';
                    }
                })
                ->addColumn('last_sync', function($device) {
                    // Affiche la date de la dernière synchronisation réelle
                    // (enregistrée à chaque sync : chargement de page ou bouton Synchroniser)
                    if (!$device->last_synced_at) {
                        return '<span class="text-muted">Jamais</span>';
                    }

                    $daysAgo = \Carbon\Carbon::parse($device->last_synced_at)->diffInDays(now());
                    $formattedDate = \Carbon\Carbon::parse($device->last_synced_at)->format('d/m/Y H:i:s');

                    if ($daysAgo == 0) {
                        return '<span class="text-success">Aujourd\'hui</span><br>
                                <small class="text-muted">' . \Carbon\Carbon::parse($device->last_synced_at)->format('H:i:s') . '</small>';
                    } elseif ($daysAgo == 1) {
                        return '<span class="text-success">Hier</span><br>
                                <small class="text-muted">' . \Carbon\Carbon::parse($device->last_synced_at)->format('H:i:s') . '</small>';
                    } elseif ($daysAgo <= 15) {
                        return '<span>' . $formattedDate . '</span><br>
                                <small class="text-success">Il y a ' . $daysAgo . ' jour(s)</small>';
                    } else {
                        return '<span>' . $formattedDate . '</span><br>
                                <small class="text-danger">Il y a ' . $daysAgo . ' jours</small>';
                    }
                })
                ->rawColumns(['status', 'last_sync'])
                ->make(true);
        }
    }

    /**
     * Applique les filtres
     */
    private function applyFilters($query, Request $request): void
    {
        // Filtre par SN (device_sn)
        if ($request->has('device_sn') && !empty($request->device_sn)) {
            $query->where('device_sn', 'LIKE', '%' . $request->device_sn . '%');
        }
        
        // Filtre par alias
        if ($request->has('alias') && !empty($request->alias)) {
            $query->where('alias', 'LIKE', '%' . $request->alias . '%');
        }

        // Filtre par IP
        if($request->has('ip') && !empty($request->ip)) {
            $query->where('ip', 'LIKE', '%' . $request->ip . '%');
        }
        
        // Filtre par zone
        if($request->has('area_name') && !empty($request->area_name)) {
            $query->where('area_name', 'LIKE', '%' . $request->area_name . '%');
        }
        
        // Filtre par terminal name
        if($request->has('terminal_name') && !empty($request->terminal_name)) {
            $query->where('terminal_name', 'LIKE', '%' . $request->terminal_name . '%');
        }
        
        // Filtre par statut (actif/inactif)
        if ($request->has('status') && !empty($request->status)) {
            $now = now();
            $fifteenDaysAgo = now()->subDays(15);
            
            if ($request->status == 'active') {
                // Actif: last_sync existe et date ≤ 15 jours
                $query->whereNotNull('last_sync')
                      ->where('last_sync', '>=', $fifteenDaysAgo);
            } elseif ($request->status == 'inactive') {
                // Inactif: pas de last_sync OU date > 15 jours
                $query->where(function($q) use ($fifteenDaysAgo) {
                    $q->whereNull('last_sync')
                      ->orWhere('last_sync', '<', $fifteenDaysAgo);
                });
            }
        }
        
        // Tri par défaut
        $query->orderBy('device_sn', 'asc');
    }

    /**
     * Synchronisation manuelle via le bouton
     */
    public function sync(Request $request)
    {
        try {
            // Récupérer le client de l'utilisateur connecté
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun client associé à votre compte.'
                ], 404);
            }
            
            $clientId = $client->id;
            $force = $request->get('force', false);
            
            // Forcer la synchronisation en ignorant le cache
            if ($force) {
                Cache::forget('devices_last_sync_' . $clientId);
                Cache::forget('devices_syncing_' . $clientId);
            }
            
            // Lancer la synchronisation
            $syncedCount = $this->syncDevicesForClientNow($clientId);
            
            return response()->json([
                'success' => true,
                'message' => "Synchronisation terminée avec succès ({$syncedCount} devices)",
                'count' => $syncedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur sync manuel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statut de synchronisation
     */
    public function syncStatus()
    {
        // Récupérer le client de l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->first();
        
        if (!$client) {
            return response()->json([
                'total_devices' => 0,
                'last_sync' => 'Jamais',
                'is_syncing' => false,
                'client_name' => 'Non associé'
            ]);
        }
        
        $clientId = $client->id;
        
        $status = [
            'total_devices' => Device::where('client_id', $clientId)->count(),
            'last_sync' => Cache::get('devices_last_sync_' . $clientId) ? 
                date('d/m/Y H:i:s', Cache::get('devices_last_sync_' . $clientId)) : 'Jamais',
            'is_syncing' => Cache::get('devices_syncing_' . $clientId, false),
            'client_name' => $client->raison_sociale ?? 'Client #' . $clientId
        ];
        
        return response()->json($status);
    }

    /**
     * Vider et resynchroniser tous les appareils du client
     */
    public function resetAndSync(Request $request)
    {
        try {
            // Récupérer le client de l'utilisateur connecté
            $client = Client::where('user_id', auth()->id())->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun client associé à votre compte.'
                ], 404);
            }
            
            $clientId = $client->id;
            
            // Vider tous les appareils du client
            Device::where('client_id', $clientId)->delete();
            Log::info("Tous les appareils du client {$clientId} ont été supprimés");
            
            // Vider le cache
            Cache::forget('devices_last_sync_' . $clientId);
            Cache::forget('devices_syncing_' . $clientId);
            
            // Resynchroniser
            $syncedCount = $this->syncDevicesForClientNow($clientId);
            
            return response()->json([
                'success' => true,
                'message' => "Base de données vidée et resynchronisée ({$syncedCount} devices)"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur resetAndSync: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}