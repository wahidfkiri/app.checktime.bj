<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;

/**
 * Résout le client dans le contexte duquel travaille l'utilisateur courant.
 *
 * Historiquement chaque contrôleur faisait `Client::where('user_id', auth()->id())`,
 * ce qui suppose que tout utilisateur possède un client. Avec l'arrivée du
 * super-admin, cette hypothèse tombe :
 *
 *   client      -> son propre client (comportement historique, inchangé)
 *   super-admin -> le client sélectionné, conservé en session
 *
 * Toute lecture du client courant doit passer par ici.
 */
class CurrentClient
{
    /** Clé de session portant le client choisi par un super-admin. */
    public const SESSION_KEY = 'admin_selected_client_id';

    private static ?Client $cache = null;
    private static ?int $cacheUserId = null;

    /**
     * Client courant, ou null si l'utilisateur n'en a pas
     * (super-admin n'ayant encore rien sélectionné).
     */
    public static function get(): ?Client
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // Cache par requête : la résolution est appelée des dizaines de fois.
        if (self::$cache !== null && self::$cacheUserId === $user->id) {
            return self::$cache;
        }

        $client = self::resolve($user);

        self::$cache = $client;
        self::$cacheUserId = $user->id;

        return $client;
    }

    /** Identifiant du client courant, ou null. */
    public static function id(): ?int
    {
        return self::get()?->id;
    }

    /** Positionne le client sur lequel travaille un super-admin. */
    public static function select(int $clientId): void
    {
        session([self::SESSION_KEY => $clientId]);
        self::forget();
    }

    /** Quitte le contexte client d'un super-admin. */
    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        self::forget();
    }

    /** Vide le cache de requête (après un changement de contexte). */
    public static function forget(): void
    {
        self::$cache = null;
        self::$cacheUserId = null;
    }

    /** Vrai si un super-admin travaille actuellement dans un client. */
    public static function isImpersonating(): bool
    {
        return Auth::user()?->hasRole('super-admin') && session()->has(self::SESSION_KEY);
    }

    private static function resolve($user): ?Client
    {
        if ($user->hasRole('super-admin')) {
            $clientId = session(self::SESSION_KEY);

            return $clientId ? Client::find($clientId) : null;
        }

        // Client (et tout autre rôle historique) : son propre client.
        return Client::where('user_id', $user->id)->first();
    }
}
