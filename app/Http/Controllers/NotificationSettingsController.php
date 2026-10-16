<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Configuration GLOBALE des notifications (SMTP + clé API SMS), réservée au
 * super-admin. Les valeurs sont stockées dans app_settings et appliquées au
 * boot (AppServiceProvider) par-dessus la config .env.
 */
class NotificationSettingsController extends Controller
{
    public function index()
    {
        return view('admin.notifications.index', [
            'mail' => AppSetting::mailConfig(),
            'smsApiKey' => AppSetting::get('sms_api_key'),
        ]);
    }

    /** Enregistrer la configuration SMTP. */
    public function smtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_host'         => 'required|string|max:255',
            'mail_port'         => 'required|integer|min:1|max:65535',
            'mail_encryption'   => 'nullable|in:tls,ssl,starttls',
            'mail_username'     => 'nullable|string|max:255',
            'mail_password'     => 'nullable|string|max:255',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name'    => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        try {
            $fields = [
                'mail_host'         => $request->input('mail_host'),
                'mail_port'         => (string) $request->input('mail_port'),
                'mail_encryption'   => $request->input('mail_encryption', ''),
                'mail_username'     => $request->input('mail_username', ''),
                'mail_from_address' => $request->input('mail_from_address'),
                'mail_from_name'    => $request->input('mail_from_name', config('app.name', 'CheckTime')),
            ];

            // Le mot de passe n'est mis à jour que s'il est fourni.
            if ($request->filled('mail_password')) {
                $fields['mail_password'] = $request->input('mail_password');
            }

            foreach ($fields as $key => $value) {
                AppSetting::set($key, $value, 'mail');
            }

            $this->applyMailConfig();
            Log::info('Configuration SMTP globale mise à jour par le super-admin #' . auth()->id());

            return response()->json(['success' => true, 'message' => 'Configuration SMTP enregistrée avec succès.']);
        } catch (\Throwable $e) {
            Log::error('Erreur enregistrement SMTP: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    /** Tester la configuration SMTP en envoyant un email. */
    public function testSmtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_host'         => 'required|string',
            'mail_port'         => 'required|integer',
            'mail_encryption'   => 'nullable|in:tls,ssl,starttls',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string',
            'mail_from_address' => 'required|email',
            'mail_from_name'    => 'nullable|string',
            'test_email'        => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        try {
            $fromAddress = $request->input('mail_from_address');
            $fromName    = $request->input('mail_from_name', config('app.name', 'CheckTime'));
            $testEmail   = $request->input('test_email');

            // Si le mot de passe n'est pas fourni, réutiliser celui enregistré.
            $password = $request->filled('mail_password')
                ? $request->input('mail_password')
                : AppSetting::get('mail_password');

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                    'host'       => $request->input('mail_host'),
                    'port'       => (int) $request->input('mail_port'),
                    'username'   => $request->input('mail_username') ?: null,
                    'password'   => $password ?: null,
                    'encryption' => $this->resolveEncryption($request->input('mail_encryption')),
                    'timeout'    => 15,
                ]),
                'mail.from' => ['address' => $fromAddress, 'name' => $fromName],
            ]);

            Mail::purge('smtp');

            Mail::raw(
                "Ceci est un email de test envoyé depuis les paramètres de notification de CheckTime.\n"
                . "Si vous recevez ce message, votre configuration SMTP est correcte.",
                function ($message) use ($testEmail, $fromAddress, $fromName) {
                    $message->from($fromAddress, $fromName)->to($testEmail)->subject('Test SMTP - CheckTime');
                }
            );

            return response()->json([
                'success' => true,
                'message' => "Message accepté par le serveur SMTP pour {$testEmail}. Vérifiez la boîte de réception (et les indésirables).",
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur test SMTP: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $this->friendlySmtpError($e)], 422);
        }
    }

    /** Enregistrer la clé API SMS. */
    public function sms(Request $request)
    {
        $validator = Validator::make($request->all(), ['sms_api_key' => 'required|string|max:512']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        try {
            AppSetting::set('sms_api_key', $request->input('sms_api_key'), 'sms');
            config(['sms.fastway.api_key' => $request->input('sms_api_key')]);
            Log::info('Clé API SMS globale mise à jour par le super-admin #' . auth()->id());

            return response()->json(['success' => true, 'message' => 'Clé API SMS enregistrée avec succès.']);
        } catch (\Throwable $e) {
            Log::error('Erreur enregistrement clé API SMS: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    /** Tester la clé API SMS (connectivité / solde). */
    public function testSms(Request $request)
    {
        $validator = Validator::make($request->all(), ['sms_api_key' => 'required|string|max:512']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $validator->errors()], 422);
        }

        try {
            config(['sms.fastway.api_key' => $request->input('sms_api_key')]);

            $service = new \App\Services\SmsService();
            $balance = $service->getBalance(true);

            if (!empty($balance['success'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connexion API SMS réussie. Solde : ' . ($balance['balance_formatted'] ?? $balance['balance'] ?? 'N/A'),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Échec du test SMS : ' . ($balance['error'] ?? 'réponse invalide de l\'API.'),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Erreur test clé API SMS: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur test SMS: ' . $e->getMessage()], 422);
        }
    }

    /** Injecte la config SMTP de la base dans la config runtime. */
    private function applyMailConfig(): void
    {
        $mail = AppSetting::mailConfig();
        if (empty($mail['mail_host'])) {
            return;
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $mail['mail_host'],
            'mail.mailers.smtp.port'       => (int) ($mail['mail_port'] ?: 587),
            'mail.mailers.smtp.username'   => $mail['mail_username'] ?: null,
            'mail.mailers.smtp.password'   => $mail['mail_password'] ?: null,
            'mail.mailers.smtp.encryption' => $this->resolveEncryption($mail['mail_encryption']),
            'mail.from.address'            => $mail['mail_from_address'] ?: null,
            'mail.from.name'               => $mail['mail_from_name'] ?: config('app.name', 'CheckTime'),
        ]);

        Mail::purge('smtp');
    }

    /** STARTTLS → null (négociation), sinon 'tls'/'ssl'. */
    private function resolveEncryption(?string $raw): ?string
    {
        return $raw === 'starttls' ? null : ($raw ?: null);
    }

    private function friendlySmtpError(\Throwable $e): string
    {
        $lower = mb_strtolower($e->getMessage());

        if (str_contains($lower, 'getaddrinfo') || str_contains($lower, 'host')) {
            return "Serveur SMTP introuvable : vérifiez le nom d'hôte.";
        }
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return "Délai de connexion dépassé : vérifiez le port et l'accès réseau.";
        }
        if (str_contains($lower, 'refused') || str_contains($lower, 'refusée')) {
            return "Connexion refusée : vérifiez le port et le chiffrement (TLS/SSL).";
        }
        if (str_contains($lower, 'auth') || str_contains($lower, '535')) {
            return "Authentification refusée : vérifiez l'identifiant et le mot de passe.";
        }
        if (str_contains($lower, 'certificate') || str_contains($lower, 'ssl') || str_contains($lower, 'tls')) {
            return "Erreur SSL/TLS : vérifiez le port et le type de chiffrement.";
        }

        return "Échec de l'envoi — vérifiez vos paramètres SMTP. Détail : " . $e->getMessage();
    }
}
