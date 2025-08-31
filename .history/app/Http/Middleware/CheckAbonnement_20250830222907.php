<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Abonnement;

class CheckAbonnement
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // On suppose que $user est un Transporteur
        if ($user && $user->role === 'transporteur') {
            $active = Abonnement::where('transporteur_id', $user->id)
                ->where('statut', 'valide')
                ->whereDate('date_fin', '>=', now())
                ->exists();

            if (!$active) {
                return response()->json([
                    'message' => 'Votre abonnement est expiré ou inexistant. Veuillez renouveler.'
                ], 403);
            }
        }

        return $next($request);
    }
}
