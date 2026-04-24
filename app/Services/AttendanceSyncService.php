<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Employee;
use App\Models\AttendanceTransaction;
use App\Models\DailyAttendance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class AttendanceSyncService
{
    private $apiBaseUrl = 'http://54.37.15.111/iclock/api/transactions/';
    
    // Configuration des timeouts et retry
    private $connectionTimeout = 10; // Timeout de connexion en secondes
    private $requestTimeout = 30; // Timeout total de la requête en secondes
    private $maxRetries = 3; // Nombre maximum de tentatives
    private $retryDelay = 1000; // Délai initial entre les tentatives en ms
    private $retryMultiplier = 2; // Multiplicateur pour délai exponentiel
    
    /**
     * Synchroniser les pointages pour tous les clients
     */
    public function syncAllClients()
    {
        $clients = Client::all();
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($clients as $client) {
            try {
                $this->syncClientAttendances($client);
                Log::info("Synchronisation terminée pour le client: {$client->nraison_sociale}");
                $successCount++;
            } catch (\Exception $e) {
                Log::error("Erreur pour le client {$client->id}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        Log::info("Synchronisation globale terminée. Succès: {$successCount}, Échecs: {$errorCount}");
    }
    
    /**
     * Synchroniser les pointages d'un client
     */
    public function syncClientAttendances(Client $client, $daysBack = 1)
    {
        $accessConfig = DB::table('access_configs')->where('client_id', $client->id)->first();
        
        if (!$accessConfig || !$accessConfig->general_token) {
            Log::warning("Client {$client->id} n'a pas de token configuré");
            return false;
        }
        
        $employees = $client->employees()->whereNotNull('emp_code')->get();
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($employees as $employee) {
            try {
                $result = $this->syncEmployeeAttendances($client, $employee, $accessConfig->general_token, $daysBack);
                if ($result) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                Log::error("Erreur pour l'employé {$employee->id} ({$employee->emp_code}): " . $e->getMessage());
                $errorCount++;
                continue;
            }
            
            // Petite pause entre chaque employé pour éviter de surcharger l'API
            usleep(100000); // 100ms
        }
        
        Log::info("Client {$client->id} - Employés synchronisés: {$successCount} succès, {$errorCount} échecs");
        
        // Mettre à jour les résumés pour les derniers jours
        $this->updateDailySummariesForPeriod($client, $daysBack);
        
        return true;
    }
    
    /**
     * Synchroniser les pointages d'un employé avec gestion des timeouts et retry
     */
    private function syncEmployeeAttendances(Client $client, Employee $employee, $token, $daysBack = 1)
    {
        $startDate = Carbon::now()->subDays($daysBack)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        
        $page = 1;
        $hasMoreData = true;
        $allTransactions = [];
        $retryCount = 0;
        
        while ($hasMoreData) {
            try {
                Log::info("Synchronisation employé {$employee->emp_code} - Page {$page}");
                
                $response = $this->makeApiRequestWithRetry(
                    $employee->emp_code,
                    $token,
                    $startDate,
                    $endDate,
                    $page
                );
                
                if ($response && isset($response['data']) && count($response['data']) > 0) {
                    $allTransactions = array_merge($allTransactions, $response['data']);
                    
                    // Vérifier s'il y a plus de pages
                    if (isset($response['next']) && $response['next'] && count($response['data']) >= 100) {
                        $page++;
                        usleep(200000); // Pause de 200ms entre les pages
                    } else {
                        $hasMoreData = false;
                    }
                } else {
                    $hasMoreData = false;
                }
                
                // Réinitialiser le compteur de retry en cas de succès
                $retryCount = 0;
                
            } catch (ConnectionException $e) {
                Log::warning("Timeout pour l'employé {$employee->emp_code} (tentative {$retryCount}): " . $e->getMessage());
                
                $retryCount++;
                if ($retryCount >= $this->maxRetries) {
                    Log::error("Échec définitif pour l'employé {$employee->emp_code} après {$this->maxRetries} tentatives");
                    $hasMoreData = false;
                } else {
                    // Attente exponentielle avant de réessayer
                    $delay = $this->retryDelay * pow($this->retryMultiplier, $retryCount - 1);
                    Log::info("Nouvelle tentative dans " . ($delay / 1000) . " secondes...");
                    usleep($delay * 1000);
                }
                
            } catch (\Exception $e) {
                Log::error("Erreur inattendue pour l'employé {$employee->emp_code}: " . $e->getMessage());
                $hasMoreData = false;
            }
        }
        
        if (!empty($allTransactions)) {
            try {
                $this->processEmployeeTransactions($client, $employee, $allTransactions);
                Log::info("Synchronisé {$employee->emp_code}: " . count($allTransactions) . " transactions");
                return true;
            } catch (\Exception $e) {
                Log::error("Erreur traitement transactions pour {$employee->emp_code}: " . $e->getMessage());
                return false;
            }
        }
        
        Log::info("Aucune transaction pour {$employee->emp_code} sur la période");
        return true; // Pas d'erreur, juste pas de données
    }
    
    /**
     * Effectuer une requête API avec gestion de timeout et retry automatique
     */
    private function makeApiRequestWithRetry($empCode, $token, $startDate, $endDate, $page = 1)
    {
        $attempt = 1;
        $lastException = null;
        
        while ($attempt <= $this->maxRetries) {
            try {
                Log::info("API Request - Employé: {$empCode}, Page: {$page}, Tentative: {$attempt}/{$this->maxRetries}");
                
                $response = Http::withHeaders([
                    'Authorization' => 'Token ' . $token,
                    'Accept' => 'application/json',
                    'User-Agent' => 'Attendance-Sync-Service/1.0'
                ])
                ->timeout($this->requestTimeout)
                ->connectTimeout($this->connectionTimeout)
                ->retry(0) // Désactiver le retry intégré de Laravel pour utiliser notre logique personnalisée
                ->get($this->apiBaseUrl, [
                    'emp_code' => $empCode,
                    'start_time' => $startDate->format('Y-m-d H:i:s'),
                    'end_time' => $endDate->format('Y-m-d H:i:s'),
                    'page' => $page,
                    'limit' => 100
                ]);
                
                if ($response->successful()) {
                    Log::info("API Success - Employé: {$empCode}, Page: {$page}, Status: " . $response->status());
                    return $response->json();
                }
                
                // Gestion des erreurs HTTP
                $statusCode = $response->status();
                $errorBody = $response->body();
                
                Log::warning("API HTTP Error - Employé: {$empCode}, Status: {$statusCode}, Response: " . substr($errorBody, 0, 200));
                
                // Ne pas réessayer pour certaines erreurs
                if (in_array($statusCode, [400, 401, 403, 404])) {
                    Log::error("Erreur fatale API - Status {$statusCode} pour {$empCode}");
                    return null;
                }
                
                // Pour les erreurs 5xx, on réessaie
                if ($statusCode >= 500) {
                    $lastException = new \Exception("HTTP {$statusCode}: " . substr($errorBody, 0, 100));
                } else {
                    return null;
                }
                
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("Timeout - Employé: {$empCode}, Tentative {$attempt}: " . $e->getMessage());
            } catch (RequestException $e) {
                $lastException = $e;
                Log::warning("Request Error - Employé: {$empCode}, Tentative {$attempt}: " . $e->getMessage());
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Exception - Employé: {$empCode}, Tentative {$attempt}: " . $e->getMessage());
            }
            
            // Calculer le délai exponentiel
            $delay = $this->retryDelay * pow($this->retryMultiplier, $attempt - 1);
            
            if ($attempt < $this->maxRetries) {
                Log::info("Nouvelle tentative pour {$empCode} dans " . ($delay / 1000) . " secondes...");
                usleep($delay * 1000);
            }
            
            $attempt++;
        }
        
        Log::error("Échec API après {$this->maxRetries} tentatives pour {$empCode}");
        return null;
    }
    
    /**
     * Traiter les transactions d'un employé
     */
    private function processEmployeeTransactions(Client $client, Employee $employee, array $transactions)
    {
        DB::beginTransaction();
        
        try {
            $processedCount = 0;
            
            foreach ($transactions as $transactionData) {
                // Valider les données minimales requises
                if (!isset($transactionData['id']) || !isset($transactionData['punch_time'])) {
                    Log::warning("Transaction invalide ignorée", $transactionData);
                    continue;
                }
                
                // Nettoyer les données
                $cleanData = $this->cleanTransactionData($transactionData);
                
                AttendanceTransaction::updateOrCreate(
                    [
                        'transaction_id' => $cleanData['id'],
                        'client_id' => $client->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'emp_code' => $cleanData['emp_code'] ?? $employee->emp_code,
                        'punch_time' => $cleanData['punch_time'],
                        'punch_state' => $cleanData['punch_state'] ?? null,
                        'verify_type' => $cleanData['verify_type'] ?? null,
                        'work_code' => $cleanData['work_code'] ?? null,
                        'terminal_sn' => $cleanData['terminal_sn'] ?? null,
                        'terminal_alias' => $cleanData['terminal_alias'] ?? null,
                        'area_alias' => $cleanData['area_alias'] ?? null,
                        'longitude' => $cleanData['longitude'] ?? null,
                        'latitude' => $cleanData['latitude'] ?? null,
                        'gps_location' => $cleanData['gps_location'] ?? null,
                        'mobile' => $cleanData['mobile'] ?? null,
                        'source' => $cleanData['source'] ?? null,
                        'purpose' => $cleanData['purpose'] ?? null,
                        'crc' => $cleanData['crc'] ?? null,
                        'is_attendance' => $cleanData['is_attendance'] ?? true,
                        'reserved' => $cleanData['reserved'] ?? null,
                        'upload_time' => isset($cleanData['upload_time']) ? Carbon::parse($cleanData['upload_time']) : null,
                        'sync_status' => $cleanData['sync_status'] ?? null,
                        'sync_time' => isset($cleanData['sync_time']) ? Carbon::parse($cleanData['sync_time']) : null,
                        'temperature' => $cleanData['temperature'] ?? null,
                        'mask_flag' => $cleanData['mask_flag'] ?? null,
                        'company' => $cleanData['company'] ?? null,
                        'terminal' => $cleanData['terminal'] ?? null,
                        'processed' => false,
                        'updated_at' => Carbon::now()
                    ]
                );
                
                $processedCount++;
            }
            
            DB::commit();
            Log::info("Transactions traitées pour {$employee->emp_code}: {$processedCount}");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur transaction DB pour {$employee->emp_code}: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Nettoyer les données de transaction
     */
    private function cleanTransactionData($data)
    {
        $cleaned = [];
        
        foreach ($data as $key => $value) {
            // Ignorer les valeurs nulles ou vides
            if ($value === null || $value === '') {
                continue;
            }
            
            // Nettoyer les chaînes de caractères
            if (is_string($value)) {
                $value = trim($value);
            }
            
            // Convertir les dates si nécessaire
            if (in_array($key, ['punch_time', 'upload_time', 'sync_time']) && is_string($value)) {
                try {
                    // S'assurer que la date est valide
                    Carbon::parse($value);
                } catch (\Exception $e) {
                    Log::warning("Date invalide pour {$key}: {$value}");
                    continue;
                }
            }
            
            $cleaned[$key] = $value;
        }
        
        return $cleaned;
    }
    
    /**
     * Mettre à jour les résumés pour une période
     */
    public function updateDailySummariesForPeriod(Client $client, $daysBack = 7)
    {
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays($daysBack);
        
        $currentDate = $startDate->copy();
        $updatedCount = 0;
        
        while ($currentDate <= $endDate) {
            try {
                $result = $this->updateDailySummaryForDate($client, $currentDate);
                if ($result) {
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Erreur résumé client {$client->id} pour {$currentDate->format('Y-m-d')}: " . $e->getMessage());
            }
            
            $currentDate->addDay();
        }
        
        Log::info("Résumés mis à jour pour le client {$client->id}: {$updatedCount} jours traités du {$startDate->format('Y-m-d')} au {$endDate->format('Y-m-d')}");
    }
    
    /**
     * Mettre à jour le résumé pour une date spécifique
     */
    public function updateDailySummaryForDate(Client $client, Carbon $date)
    {
        $employees = $client->employees()->whereNotNull('emp_code')->get();
        $updatedCount = 0;
        
        foreach ($employees as $employee) {
            try {
                $result = $this->updateEmployeeDailySummary($client, $employee, $date);
                if ($result) {
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Erreur résumé pour {$employee->emp_code} le {$date->format('Y-m-d')}: " . $e->getMessage());
                continue;
            }
        }
        
        Log::info("Résumé du {$date->format('Y-m-d')} pour client {$client->id}: {$updatedCount}/" . $employees->count() . " employés mis à jour");
        
        return $updatedCount;
    }
    
    /**
     * Mettre à jour le résumé quotidien d'un employé (votre code existant)
     */
    private function updateEmployeeDailySummary(Client $client, Employee $employee, Carbon $date)
    {
        // Votre code existant ici...
        // (Je garde votre implémentation actuelle)
        
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();
        
        // Récupérer TOUTES les transactions de la journée
        $transactions = AttendanceTransaction::where('client_id', $client->id)
            ->where('employee_id', $employee->id)
            ->whereBetween('punch_time', [$startOfDay, $endOfDay])
            ->orderBy('punch_time')
            ->get();
        
        // Si aucune transaction pour ce jour
        if ($transactions->isEmpty()) {
            return $this->updateOrCreateAbsentRecord($client, $employee, $date);
        }
        
        // Grouper les transactions par jour (au cas où il y aurait des données erronées)
        $dailyTransactions = $transactions->filter(function ($transaction) use ($date) {
            return Carbon::parse($transaction->punch_time)->isSameDay($date);
        });
        
        if ($dailyTransactions->isEmpty()) {
            return $this->updateOrCreateAbsentRecord($client, $employee, $date);
        }
        
        // Calculer les statistiques
        $stats = $this->calculateDailyStats($dailyTransactions, $date);
        
        // Préparer raw_data avec TOUTES les données
        $rawData = $dailyTransactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'punch_time' => $transaction->punch_time->format('Y-m-d H:i:s'),
                'punch_state' => $transaction->punch_state,
                'verify_type' => $transaction->verify_type,
                'terminal_alias' => $transaction->terminal_alias,
                'area_alias' => $transaction->area_alias,
                'upload_time' => $transaction->upload_time ? $transaction->upload_time->format('Y-m-d H:i:s') : null,
                'source' => $transaction->source,
                'purpose' => $transaction->purpose
            ];
        })->toArray();
        
        // Mettre à jour ou créer le résumé quotidien
        $dailyAttendance = DailyAttendance::updateOrCreate(
            [
                'client_id' => $client->id,
                'employee_id' => $employee->id,
                'attendance_date' => $date->format('Y-m-d')
            ],
            [
                'emp_code' => $employee->emp_code,
                'check_in' => $stats['check_in'],
                'check_out' => $stats['check_out'],
                'total_punches' => $stats['total_punches'],
                'punch_times' => json_encode($stats['punch_times']),
                'work_hours' => $stats['work_hours'],
                'break_hours' => $stats['break_hours'],
                'effective_hours' => $stats['effective_hours'],
                'overtime_hours' => $stats['overtime_hours'],
                'status' => $stats['status'],
                'is_late' => $stats['is_late'],
                'late_minutes' => $stats['late_minutes'],
                'is_early_leave' => $stats['is_early_leave'],
                'early_minutes' => $stats['early_minutes'],
                'is_overtime' => $stats['is_overtime'],
                'is_short_work' => $stats['is_short_work'],
                'short_hours' => $stats['short_hours'],
                'has_multiple_punches' => $stats['has_multiple_punches'],
                'multiple_punches_count' => $stats['multiple_punches_count'],
                'raw_data' => json_encode($rawData), // TOUTES les données brutes
                'notes' => $stats['notes'],
                'updated_at' => Carbon::now(),
                'last_sync_at' => Carbon::now()
            ]
        );
        
        // Marquer les transactions comme traitées
        AttendanceTransaction::whereIn('id', $dailyTransactions->pluck('id'))
            ->update([
                'processed' => true,
                'daily_attendance_id' => $dailyAttendance->id,
                'processed_at' => Carbon::now()
            ]);
        
        return $dailyAttendance;
    }
    
    /**
     * Calculer les statistiques quotidiennes à partir des transactions (votre code existant)
     */
    private function calculateDailyStats($transactions, Carbon $date)
    {
        // Votre code existant ici...
        $punchTimes = $transactions->pluck('punch_time')->map(function ($time) {
            return Carbon::parse($time);
        })->sort();
        
        $totalPunches = $punchTimes->count();
        $punchTimesArray = $punchTimes->map(function ($time) {
            return $time->format('H:i:s');
        })->toArray();
        
        // 1. Check-in = premier pointage de la journée
        $checkIn = $punchTimes->first();
        
        // 2. Check-out = dernier pointage de la journée
        $checkOut = $punchTimes->last();
        
        // 3. Calculer les heures de travail INTELLIGENT (gestion des pauses multiples)
        $workHours = $this->calculateSmartWorkHours($punchTimes, $date);
        
        // 4. Heures attendues (8h - 1h pause = 7h effectives)
        $expectedWorkHours = 7;
        $expectedTotalHours = 8; // 8h avec pause
        
        // 5. Analyser les pointages
        $stats = [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_punches' => $totalPunches,
            'punch_times' => $punchTimesArray,
            'work_hours' => $workHours,
            'break_hours' => max(0, $expectedTotalHours - $expectedWorkHours),
            'effective_hours' => $workHours,
            'overtime_hours' => 0,
            'status' => 'PRESENT',
            'is_late' => false,
            'late_minutes' => 0,
            'is_early_leave' => false,
            'early_minutes' => 0,
            'is_overtime' => false,
            'is_short_work' => false,
            'short_hours' => 0,
            'has_multiple_punches' => $totalPunches > 2,
            'multiple_punches_count' => $totalPunches,
            'notes' => ''
        ];
        
        // 6. Vérifier les retards (après 8h)
        $expectedCheckIn = $date->copy()->setTime(8, 0, 0);
        if ($checkIn && $checkIn->gt($expectedCheckIn)) {
            $lateMinutes = $expectedCheckIn->diffInMinutes($checkIn);
            $stats['late_minutes'] = $lateMinutes;
            
            if ($lateMinutes > 15) {
                $stats['is_late'] = true;
                $stats['status'] = 'LATE';
                $stats['notes'] .= "Retard de {$lateMinutes} minutes. ";
            }
        }
        
        // 7. Vérifier les départs anticipés (avant 17h)
        $expectedCheckOut = $date->copy()->setTime(17, 0, 0);
        if ($checkOut && $checkOut->lt($expectedCheckOut)) {
            $earlyMinutes = $checkOut->diffInMinutes($expectedCheckOut);
            $stats['early_minutes'] = $earlyMinutes;
            
            if ($earlyMinutes > 30 && $workHours < $expectedWorkHours) {
                $stats['is_early_leave'] = true;
                $stats['status'] = 'EARLY_LEAVE';
                $stats['notes'] .= "Départ anticipé de {$earlyMinutes} minutes. ";
            }
        }
        
        // 8. Vérifier les heures supplémentaires
        if ($workHours > $expectedWorkHours) {
            $overtime = $workHours - $expectedWorkHours;
            $stats['overtime_hours'] = round($overtime, 2);
            
            if ($overtime > 0.5) { // Plus de 30 minutes
                $stats['is_overtime'] = true;
                $stats['status'] = 'OVERTIME';
                $stats['notes'] .= "Heures supplémentaires: {$stats['overtime_hours']}h. ";
            }
        }
        
        // 9. Vérifier travail court
        if ($workHours < ($expectedWorkHours - 1)) { // Moins de 6h
            $stats['is_short_work'] = true;
            $stats['short_hours'] = round($expectedWorkHours - $workHours, 2);
            $stats['status'] = 'SHORT_WORK';
            $stats['notes'] .= "Travail court: {$stats['short_hours']}h manquantes. ";
        }
        
        // 10. Vérifier demi-journée
        if ($totalPunches == 1) {
            $stats['status'] = 'HALF_DAY';
            $stats['notes'] .= "Un seul pointage. ";
        } elseif ($totalPunches % 2 != 0) {
            // Nombre impair de pointages
            $stats['status'] = 'IRREGULAR';
            $stats['notes'] .= "Nombre impair de pointages ({$totalPunches}). ";
        }
        
        // 11. Vérifier les pointages multiples anormaux
        if ($totalPunches > 4) {
            $stats['notes'] .= "Multiple pointages ({$totalPunches}) détectés. ";
        }
        
        // 12. Calculer les heures effectives (sans les pauses internes)
        $stats['effective_hours'] = $this->calculateEffectiveHours($punchTimes, $date);
        
        return $stats;
    }
    
    /**
     * Calculer les heures de travail intelligentes (votre code existant)
     */
    private function calculateSmartWorkHours($punchTimes, Carbon $date)
    {
        if ($punchTimes->count() < 2) {
            return 0;
        }
        
        $sorted = $punchTimes->values();
        $totalMinutes = 0;
        
        // Algorithme pour gérer les pointages pairs/impairs
        for ($i = 0; $i < count($sorted) - 1; $i++) {
            $current = $sorted[$i];
            $next = $sorted[$i + 1];
            
            // Si la différence est raisonnable (pas une pause déjeuner)
            $diffMinutes = $current->diffInMinutes($next);
            
            if ($diffMinutes <= 90) { // Moins de 1h30 = temps de travail
                $totalMinutes += $diffMinutes;
            } elseif ($i == 0 && $diffMinutes > 90) {
                // Première longue pause = probable pause déjeuner, ignorer
                continue;
            }
        }
        
        // Si nombre impair, traiter le dernier pointage
        if (count($sorted) % 2 != 0 && count($sorted) > 2) {
            $lastIndex = count($sorted) - 1;
            $prevIndex = $lastIndex - 1;
            
            $prevTime = $sorted[$prevIndex];
            $lastTime = $sorted[$lastIndex];
            
            $diffMinutes = $prevTime->diffInMinutes($lastTime);
            
            // Si le dernier pointage est après 16h et la différence est raisonnable
            if ($diffMinutes <= 240 && $lastTime->hour >= 16) { // 4 heures max
                $totalMinutes += $diffMinutes;
            }
        }
        
        return round($totalMinutes / 60, 2);
    }
    
    /**
     * Calculer les heures effectives (votre code existant)
     */
    private function calculateEffectiveHours($punchTimes, Carbon $date)
    {
        if ($punchTimes->count() < 2) {
            return 0;
        }
        
        $sorted = $punchTimes->values();
        $totalMinutes = 0;
        
        // Prendre seulement les 2 premiers et 2 derniers pointages pour les heures normales
        if ($sorted->count() >= 4) {
            // Premier check-in et check-out du matin
            $morningIn = $sorted[0];
            $morningOut = $sorted[1];
            
            // Dernier check-in et check-out de l'après-midi
            $afternoonIn = $sorted[$sorted->count() - 2];
            $afternoonOut = $sorted[$sorted->count() - 1];
            
            // Calculer les périodes
            $morningMinutes = $morningIn->diffInMinutes($morningOut);
            $afternoonMinutes = $afternoonIn->diffInMinutes($afternoonOut);
            
            $totalMinutes = $morningMinutes + $afternoonMinutes;
        } else {
            // Si moins de 4 pointages, prendre du premier au dernier
            $totalMinutes = $sorted->first()->diffInMinutes($sorted->last());
        }
        
        // Soustraction de la pause déjeuner si la journée est complète
        if ($totalMinutes > 240) { // Plus de 4h
            $totalMinutes -= 60; // 1h de pause déjeuner
        }
        
        return round($totalMinutes / 60, 2);
    }
    
    /**
     * Créer/mettre à jour un enregistrement d'absence (votre code existant)
     */
    private function updateOrCreateAbsentRecord(Client $client, Employee $employee, Carbon $date)
    {
        // Vérifier si jour de semaine
        if ($date->isWeekend()) {
            // Supprimer si existant
            DailyAttendance::where([
                'client_id' => $client->id,
                'employee_id' => $employee->id,
                'attendance_date' => $date->format('Y-m-d')
            ])->delete();
            return null;
        }
        
        // Vérifier les congés
        $hasLeave = $this->checkEmployeeLeave($employee, $date);
        
        $status = $hasLeave ? 'LEAVE' : 'ABSENT';
        $notes = $hasLeave ? 'Congé enregistré' : 'Aucun pointage';
        
        return DailyAttendance::updateOrCreate(
            [
                'client_id' => $client->id,
                'employee_id' => $employee->id,
                'attendance_date' => $date->format('Y-m-d')
            ],
            [
                'emp_code' => $employee->emp_code,
                'status' => $status,
                'notes' => $notes,
                'raw_data' => json_encode([]),
                'updated_at' => Carbon::now()
            ]
        );
    }
    
    /**
     * Vérifier si l'employé est en congé (votre code existant)
     */
    private function checkEmployeeLeave(Employee $employee, Carbon $date)
    {
        // À implémenter selon votre système de congés
        return false;
    }
    
    /**
     * Synchroniser pour une date spécifique
     */
    public function syncForDate(Carbon $date)
    {
        $clients = Client::all();
        $successCount = 0;
        
        foreach ($clients as $client) {
            try {
                $result = $this->syncClientForDate($client, $date);
                if ($result) {
                    $successCount++;
                }
                Log::info("Synchronisé pour {$date->format('Y-m-d')} - client: {$client->nraison_sociale}");
            } catch (\Exception $e) {
                Log::error("Erreur client {$client->id} le {$date->format('Y-m-d')}: " . $e->getMessage());
            }
        }
        
        Log::info("Synchronisation date {$date->format('Y-m-d')} terminée: {$successCount}/" . $clients->count() . " clients réussis");
    }
    
    /**
     * Synchroniser un client pour une date
     */
    private function syncClientForDate(Client $client, Carbon $date)
    {
        $accessConfig = DB::table('access_configs')->where('client_id', $client->id)->first();
        
        if (!$accessConfig || !$accessConfig->general_token) {
            return false;
        }
        
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();
        
        $employees = $client->employees()->whereNotNull('emp_code')->get();
        $successCount = 0;
        
        foreach ($employees as $employee) {
            try {
                $result = $this->syncEmployeeForDate($client, $employee, $accessConfig->general_token, $startDate, $endDate);
                if ($result) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                Log::error("Erreur {$employee->emp_code} le {$date->format('Y-m-d')}: " . $e->getMessage());
                continue;
            }
            
            usleep(50000); // 50ms entre chaque employé
        }
        
        Log::info("Client {$client->id} - Date {$date->format('Y-m-d')}: {$successCount}/" . $employees->count() . " employés synchronisés");
        
        // Mettre à jour les résumés
        $this->updateDailySummaryForDate($client, $date);
        
        return true;
    }
    
    /**
     * Synchroniser un employé pour une date avec gestion de timeout
     */
    private function syncEmployeeForDate(Client $client, Employee $employee, $token, Carbon $startDate, Carbon $endDate)
    {
        try {
            $response = $this->makeApiRequestWithRetry(
                $employee->emp_code,
                $token,
                $startDate,
                $endDate,
                1 // Page 1 uniquement pour une date spécifique
            );
            
            if ($response && isset($response['data']) && count($response['data']) > 0) {
                $this->processEmployeeTransactions($client, $employee, $response['data']);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("Erreur syncEmployeeForDate {$employee->emp_code}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir le résumé des pointages (votre code existant)
     */
    public function getEmployeeAttendanceSummary(Client $client, Employee $employee, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();
        
        $attendances = DailyAttendance::where('client_id', $client->id)
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date', 'desc')
            ->get();
        
        // Statistiques
        $stats = [
            'total_days' => $attendances->count(),
            'present_days' => $attendances->whereIn('status', ['PRESENT', 'LATE', 'OVERTIME'])->count(),
            'absent_days' => $attendances->where('status', 'ABSENT')->count(),
            'late_days' => $attendances->where('status', 'LATE')->count(),
            'half_days' => $attendances->where('status', 'HALF_DAY')->count(),
            'leave_days' => $attendances->where('status', 'LEAVE')->count(),
            'total_work_hours' => round($attendances->sum('work_hours'), 2),
            'total_overtime' => round($attendances->sum('overtime_hours'), 2),
            'avg_work_hours' => round($attendances->avg('work_hours'), 2),
        ];
        
        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'employee' => [
                'id' => $employee->id,
                'emp_code' => $employee->emp_code,
                'name' => $employee->first_name . ' ' . $employee->last_name
            ],
            'stats' => $stats,
            'attendances' => $attendances->map(function ($attendance) {
                return [
                    'date' => $attendance->attendance_date,
                    'check_in' => $attendance->check_in ? Carbon::parse($attendance->check_in)->format('H:i') : null,
                    'check_out' => $attendance->check_out ? Carbon::parse($attendance->check_out)->format('H:i') : null,
                    'work_hours' => $attendance->work_hours,
                    'status' => $attendance->status,
                    'total_punches' => $attendance->total_punches,
                    'is_late' => $attendance->is_late,
                    'is_overtime' => $attendance->is_overtime,
                    'notes' => $attendance->notes,
                    'raw_data_count' => $attendance->raw_data ? count(json_decode($attendance->raw_data, true)) : 0
                ];
            })
        ];
    }
    
    /**
     * Nettoyer les anciennes données (votre code existant)
     */
    public function cleanupOldTransactions($daysToKeep = 60)
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        $deleted = AttendanceTransaction::where('created_at', '<', $cutoffDate)
            ->delete();
        
        Log::info("Nettoyage: {$deleted} transactions supprimées (avant {$cutoffDate->format('Y-m-d')})");
        
        return $deleted;
    }
}