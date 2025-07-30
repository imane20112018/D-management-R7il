<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Transporteur;
use Illuminate\Support\Str;

class ClientGoogleController extends Controller
{
    public function redirectToGoogle()
    {
        config([
            'services.google.client_id' => env('GOOGLE_CLIENT_ID_CLIENT'),
            'services.google.client_secret' => env('GOOGLE_CLIENT_SECRET_CLIENT'),
            'services.google.redirect' => env('GOOGLE_REDIRECT_URI_CLIENT'),
        ]);

        return Socialite::driver('google')->stateless()->redirect();
    }

public function handleGoogleCallback()
{
    config([
        'services.google.client_id' => env('GOOGLE_CLIENT_ID_CLIENT'),
        'services.google.client_secret' => env('GOOGLE_CLIENT_SECRET_CLIENT'),
        'services.google.redirect' => env('GOOGLE_REDIRECT_URI_CLIENT'),
    ]);

    try {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $userEmail = $googleUser->getEmail();
        $ip = request()->ip();

        // Vérifier s’il existe déjà comme transporteur (type différent)
        $existingTransporteur = Transporteur::where('email', $userEmail)
            ->where('type', 'transporteur')
            ->first();

        if ($existingTransporteur) {
            return redirect()->away('http://localhost:5173/login_client?error=already_registered_as_transporteur');
        }

        // Chercher client existant
        $client = Transporteur::where('email', $userEmail)
            ->where('type', 'client')
            ->first();

        if ($client) {
            // Mettre à jour l'adresse IP à chaque connexion Google réussie
            $client->adresse_ip = $ip;
            $client->save();
        } else {
            // Créer un nouveau client
            $client = Transporteur::create([
                'email' => $userEmail,
                'nom' => $googleUser->getName(),
                'password' => bcrypt(Str::random(24)),
                'type' => 'client',
                'statut_validation' => 'en_attente',
                'date_inscription' => now(),
                'email_verified_at' => now(),
                'abonnement_actif' => 'free_14_days',
                'adresse_ip' => $ip,
            ]);
        }

        $token = $client->createToken('client-token')->plainTextToken;

        return redirect()->away("http://localhost:5173/google-login-success?token={$token}");

    } catch (\Exception $e) {
        return redirect()->away('http://localhost:5173/login_client?error=google_exception');
    }
}


}
