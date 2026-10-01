<?php

namespace App\Http\Controllers;

use App\Services\BiometricService;
use Illuminate\Http\Request;

class BiometricController extends Controller
{
    protected $biometricService;
    
    public function __construct(BiometricService $biometricService)
    {
        $this->biometricService = $biometricService;
    }
    
    /**
     * Récupérer les transactions avec vérification biométrique
     */
    public function getTransactions(Request $request)
    {
        try {
            $user = \Auth::user();
            $client = $user->client; // Assurez-vous que l'utilisateur a une relation
            $access_configs = \App\Models\AccessConfig::where('client_id', $client->id)->first();
            $generalToken = $access_configs->general_token ?? null;
            $response = $this->biometricService->getTransactions($request, $generalToken);
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Générer une réponse biométrique pour un employé spécifique.
     * Identification par employee_id (id externe unique), scoppé au client
     * connecté — les emp_code peuvent être en doublon.
     */
    public function getBiometricVerification($id)
    {
        $client = \App\Models\Client::where('user_id', auth()->id())->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'error'   => 'Aucun client associé à votre compte.',
            ], 403);
        }

        $employee = \App\Models\Employee::where('employee_id', $id)
            ->where('client_id', $client->id)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'error'   => "Employé introuvable (employee_id {$id})",
            ], 404);
        }

        $biometricData = $this->biometricService->generateBiometricResponseForEmployee($employee);

        return response()->json($biometricData);
    }
}