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

            // Vérifie s’il existe déjà comme transporteur
            $existingTransporteur = Transporteur::where('email', $googleUser->getEmail())->where('type', 'transporteur')->first();
            if ($existingTransporteur) {
                return redirect()->away('http://localhost:5173/login_client?error=already_registered_as_transporteur');
            }

            // Récupère ou crée le client
            $client = Transporteur::firstOrCreate(
                ['email' => $googleUser->getEmail(), 'type' => 'client'],
                [
                    'nom' => $googleUser->getName(),
                    'password' => bcrypt(Str::random(24)),
                    'type' => 'client',
                    'statut_validation' => 'en_attente',
                    'date_inscription' => now(),
                    'abonnement_actif' => false,
                ]
            );

            $token = $client->createToken('client-token')->plainTextToken;

            return redirect()->away("http://localhost:5173/google-login-success?token={$token}");
        } catch (\Exception $e) {
            return redirect()->away('http://localhost:5173/login_client?error=google_exception');
        }
    }
}
