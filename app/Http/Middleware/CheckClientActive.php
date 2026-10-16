<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Client; // Assuming your Client model
use Illuminate\Support\Facades\Auth;

class CheckClientActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Résout le client courant : client propriétaire, ou client sélectionné
        // par un super-admin (impersonation). Centralisé dans CurrentClient.
        $client = \App\Support\CurrentClient::get();

        // Check if client exists and is active
        if (!$client) {
            // Un super-admin n'a pas de client propre : il doit d'abord en
            // « entrer » un. On l'oriente vers l'espace d'administration plutôt
            // que de renvoyer une erreur brute.
            if ($user->hasRole('super-admin')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message'  => 'Sélectionnez un client pour continuer.',
                        'redirect' => route('admin.clients.index'),
                    ], 409);
                }

                return redirect()->route('admin.clients.index')
                    ->with('error', 'Sélectionnez un client pour accéder à cet écran.');
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Client profile not found.'], 403);
            }

            abort(403, 'Profil client introuvable.');
        }

        if (!$client->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your client account is inactive. Please contact support.'], 403);
            }

            abort(403, 'Votre compte client est inactif. Contactez le support.');
        }
        
        // Optionally, you can share the client data with the request
        $request->merge(['client' => $client]);
        
        return $next($request);
    }
}