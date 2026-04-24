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
        
        // Find the client associated with this user
        $client = Client::where('user_id', $user->id)->first();
        
        // Check if client exists and is active
        if (!$client) {
            return response()->json([
                'message' => 'Client profile not found.'
            ], 403);
        }
        
        if (!$client->is_active) {
            return response()->json([
                'message' => 'Your client account is inactive. Please contact support.'
            ], 403);
        }
        
        // Optionally, you can share the client data with the request
        $request->merge(['client' => $client]);
        
        return $next($request);
    }
}