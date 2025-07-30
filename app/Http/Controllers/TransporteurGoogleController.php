<?php

namespace App\Http\Controllers;

use App\Models\Transporteur;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class TransporteurGoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    // TransporteurGoogleController.php

   public function handleGoogleCallback()
{
    // 🛠 Config dynamique Google TRANSPORTEUR
    config([
        'services.google.client_id' => env('GOOGLE_CLIENT_ID_TRANSPORTEUR'),
        'services.google.client_secret' => env('GOOGLE_CLIENT_SECRET_TRANSPORTEUR'),
        'services.google.redirect' => env('GOOGLE_REDIRECT_URI_TRANSPORTEUR'),
    ]);

    try {
        $googleUser = Socialite::driver('google')->stateless()->user();

        // 🔒 Vérifier si l'utilisateur existe comme CLIENT
        $existingClient = Transporteur::where('email', $googleUser->getEmail())
                            ->where('type', 'client')->first();

        if ($existingClient) {
            return redirect()->away("http://localhost:5173/login_client?error=already_registered_as_client");
        }

        // ✅ Créer ou récupérer le transporteur
        $transporteur = Transporteur::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'nom' => $googleUser->getName(),
                'password' => bcrypt(Str::random(16)),
                'type' => 'transporteur',
                'statut_validation' => 'en_attente',
                'date_inscription' => now(),
                'abonnement_actif' => 'free_14_days',
                'email_verified_at' => now(),
            ]
        );

        $token = $transporteur->createToken('authToken')->plainTextToken;

        return redirect()->away("http://localhost:5173/google-login-success?token={$token}");

    } catch (\Exception $e) {
        return redirect()->away("http://localhost:5173/login_client?error=google_exception");
    }
}

}
