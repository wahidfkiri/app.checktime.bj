<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    /**
     * Display the form to request a password reset link.
     *
     * @return \Illuminate\View\View
     */
    public function showLinkRequestForm()
    {
        // Affiche la page HTML pour "Mot de passe oublié"
        return view('auth.passwords.email');
    }

    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        
        Log::info('Password reset request received for email: ' . $request->email);
        
        try {
            // Vérifier si l'utilisateur existe
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                Log::warning('Password reset attempt for non-existent email: ' . $request->email);
                
                // Pour des raisons de sécurité, on retourne toujours un succès
                // même si l'email n'existe pas
                return back()->with('status', 
                    'Si votre adresse email est enregistrée, vous recevrez un lien de réinitialisation.'
                );
            }
            
            // Vérifier si l'utilisateur est admin
            if (!$this->isClient($user)) {
                Log::warning('Password reset attempt for non-admin user: ' . $request->email);
                return back()->withErrors([
                    'email' => 'Cette fonctionnalité est réservée aux clients.'
                ]);
            }
            
            Log::info('User found and is client, sending reset link for: ' . $request->email);
            
            // Envoyer le lien de réinitialisation
            $status = Password::sendResetLink(
                $request->only('email')
            );
            
            Log::info('Password reset link status for ' . $request->email . ': ' . $status);
            
            return $status === Password::RESET_LINK_SENT
                ? back()->with('status', __($status))
                : back()->withErrors(['email' => __($status)]);
            
        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return back()->withErrors([
                'email' => 'Une erreur technique est survenue. Veuillez réessayer.'
            ]);
        }
    }
    
    /**
     * Check if user is admin
     */
    private function isClient(User $user)
    {
        // Adaptez cette méthode selon votre système de rôles
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('client');
        }
        
        if (property_exists($user, 'role') || isset($user->role)) {
            return $user->role === 'client';
        }
        
        if (method_exists($user, 'roles')) {
            return $user->roles()->where('name', 'client')->exists();
        }
        
        if (property_exists($user, 'is_client') || isset($user->is_client)) {
            return (bool) $user->is_client;
        }
        
        // Si aucun système de rôle n'est défini, autorisez tous les utilisateurs
        return true; // À adapter
    }
}