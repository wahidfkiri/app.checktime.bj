<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Telescope (outil de debug) uniquement en local, et seulement si le
        // package est installé (il est en require-dev, donc absent en prod avec
        // `composer install --no-dev`). class_exists évite le crash sinon.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\Telescope::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->applyGlobalNotificationConfig();
    }

    /**
     * Applique la configuration globale (SMTP + clé API SMS) stockée en base
     * par le super-admin, par-dessus la config .env. Sans danger : no-op si la
     * table n'existe pas encore ou si aucune valeur n'est renseignée.
     */
    private function applyGlobalNotificationConfig(): void
    {
        try {
            $mail = \App\Models\AppSetting::mailConfig();

            if (!empty($mail['mail_host'])) {
                $encryption = $mail['mail_encryption'] === 'starttls' ? null : ($mail['mail_encryption'] ?: null);

                config([
                    'mail.default'                 => 'smtp',
                    'mail.mailers.smtp.host'       => $mail['mail_host'],
                    'mail.mailers.smtp.port'       => (int) ($mail['mail_port'] ?: 587),
                    'mail.mailers.smtp.username'   => $mail['mail_username'] ?: null,
                    'mail.mailers.smtp.password'   => $mail['mail_password'] ?: null,
                    'mail.mailers.smtp.encryption' => $encryption,
                ]);

                if (!empty($mail['mail_from_address'])) {
                    config([
                        'mail.from.address' => $mail['mail_from_address'],
                        'mail.from.name'    => $mail['mail_from_name'] ?: config('app.name', 'CheckTime'),
                    ]);
                }
            }

            $smsKey = \App\Models\AppSetting::get('sms_api_key');
            if (!empty($smsKey)) {
                config(['sms.fastway.api_key' => $smsKey]);
            }
        } catch (\Throwable $e) {
            // Ne jamais bloquer le boot pour un problème de config optionnelle.
            \Illuminate\Support\Facades\Log::warning('applyGlobalNotificationConfig: ' . $e->getMessage());
        }
    }
}
