<?php

namespace App\Http\Controllers;

use App\Models\ScheduledNotification;
use App\Support\CurrentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Gestion PAR CLIENT des tâches planifiées (rapports). Scopé CurrentClient.
 * L'exécution immédiate lance la commande avec --client={client courant}.
 */
class ScheduledNotificationController extends Controller
{
    /** Commandes autorisées à l'exécution/planification depuis l'interface. */
    private const ALLOWED_COMMANDS = [
        'attendance:send-weekly-reports',
        'attendance:send-weekly-rh-reports',
        'reports:send-monthly-rh',
        'attendance:send-weekly-sms',
    ];

    public function index()
    {
        $jobs = ScheduledNotification::where('client_id', CurrentClient::id())
            ->orderBy('label')
            ->get();

        return view('notifications.index', compact('jobs'));
    }

    public function updateJobs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jobs'                   => 'required|array',
            'jobs.*.id'              => 'required|integer',
            'jobs.*.is_active'       => 'required|boolean',
            'jobs.*.frequency'       => 'required|in:daily,weekly,monthly,custom,once',
            'jobs.*.time'            => 'nullable|date_format:H:i',
            'jobs.*.day_of_week'     => 'nullable|integer|between:1,7',
            'jobs.*.day_of_month'    => 'nullable|integer|between:1,31',
            'jobs.*.cron_expression' => 'nullable|string|max:100',
            'jobs.*.run_at'          => 'nullable|date',
            'jobs.*.recipients'      => 'nullable|array',
            'jobs.*.recipients.*'    => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        try {
            $clientId = CurrentClient::id();

            foreach ($request->input('jobs', []) as $data) {
                // Scoping strict : uniquement les tâches du client courant.
                $job = ScheduledNotification::where('client_id', $clientId)->find($data['id']);
                if (!$job) {
                    continue;
                }

                $job->is_active       = (bool) $data['is_active'];
                $job->frequency       = $data['frequency'];
                $job->time            = $data['time'] ?? '09:00';
                $job->day_of_week     = $data['frequency'] === 'weekly' ? ($data['day_of_week'] ?? 1) : null;
                $job->day_of_month    = $data['frequency'] === 'monthly' ? ($data['day_of_month'] ?? 1) : null;
                $job->cron_expression = $data['frequency'] === 'custom' ? ($data['cron_expression'] ?? null) : null;

                if ($data['frequency'] === 'once') {
                    $job->run_at = !empty($data['run_at']) ? \Carbon\Carbon::parse($data['run_at']) : null;
                    $job->last_run_at = null;
                    $job->last_status = null;
                } else {
                    $job->run_at = null;
                }

                if ($job->supports_recipients) {
                    $recipients = array_values(array_filter(array_map('trim', $data['recipients'] ?? [])));
                    $job->recipients = !empty($recipients) ? $recipients : null;
                }

                $job->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Tâches planifiées enregistrées avec succès.',
                'jobs'    => ScheduledNotification::where('client_id', $clientId)->get(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur updateJobs: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    public function runJob(Request $request)
    {
        $command = $request->input('command');

        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            return response()->json(['success' => false, 'message' => 'Commande non autorisée.'], 403);
        }

        $clientId = CurrentClient::id();
        if (!$clientId) {
            return response()->json(['success' => false, 'message' => 'Aucun client courant.'], 409);
        }

        try {
            @set_time_limit(0);

            Artisan::call($command, ['--client' => $clientId]);
            $output = trim(Artisan::output());

            ScheduledNotification::where('client_id', $clientId)->where('command', $command)->update([
                'last_run_at' => now(),
                'last_status' => 'Exécutée manuellement',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande exécutée : ' . $command,
                'output'  => $output !== '' ? $output : '(aucune sortie)',
            ]);
        } catch (\Throwable $e) {
            Log::error("Erreur runJob {$command}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur exécution: ' . $e->getMessage()], 500);
        }
    }
}
