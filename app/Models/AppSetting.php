<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètre GLOBAL de la plateforme (clé/valeur), géré par le super-admin.
 * Sert notamment à la configuration SMTP et à la clé API SMS communes.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value', 'group'];

    /** Lecture d'une valeur (robuste si la table n'existe pas encore). */
    public static function get(string $key, $default = null)
    {
        if (!Schema::hasTable('app_settings')) {
            return $default;
        }

        $row = static::where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    /** Écriture d'une valeur. */
    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    /** Toutes les valeurs d'un groupe, en tableau associatif key => value. */
    public static function group(string $group): array
    {
        if (!Schema::hasTable('app_settings')) {
            return [];
        }

        return static::where('group', $group)->pluck('value', 'key')->toArray();
    }

    /** Configuration mail courante (clés mail_*), avec valeurs par défaut. */
    public static function mailConfig(): array
    {
        $mail = static::group('mail');

        return [
            'mail_host'         => $mail['mail_host'] ?? null,
            'mail_port'         => $mail['mail_port'] ?? null,
            'mail_encryption'   => $mail['mail_encryption'] ?? null,
            'mail_username'     => $mail['mail_username'] ?? null,
            'mail_password'     => $mail['mail_password'] ?? null,
            'mail_from_address' => $mail['mail_from_address'] ?? null,
            'mail_from_name'    => $mail['mail_from_name'] ?? null,
        ];
    }
}
