<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use App\Models\Client;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }


public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:6',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $credentials = $request->only('email', 'password');
    $remember = $request->has('remember');

    // Essayer d'authentifier
    if (Auth::attempt($credentials, $remember)) {

        $user = Auth::user();

        
        // Check if user has a client record and if it's active
        $client = Client::where('user_id', $user->id)->first();

        if (!$client->is_active) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Votre compte client est désactivé. Contactez l\'administration.',
                'is_active' => false,
                'client_data' => [
                    'id' => $client->id,
                    'deactivated_at' => $client->deactivated_at // optional
                ]
            ], 403);
        }

        // Vérifier le rôle admin
        if (!$user->hasRole('client')) {
            Auth::logout(); // déconnecter l'utilisateur
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à vous connecter ici.'
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('dashboard'),
            'message' => 'Connexion réussie!'
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Les identifiants ne correspondent pas à nos enregistrements.'
    ], 401);
}


    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}