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
    config([
        'services.google.client_id' => env('GOOGLE_CLIENT_ID_TRANSPORTEUR'),
        'services.google.client_secret' => env('GOOGLE_CLIENT_SECRET_TRANSPORTEUR'),
        'services.google.redirect' => env('GOOGLE_REDIRECT_URI_TRANSPORTEUR'),
    ]);

    try {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $userEmail = $googleUser->getEmail();
        $ip = request()->ip();

        // Vérifie si l'utilisateur existe déjà comme CLIENT (type différent)
        $existingClient = Transporteur::where('email', $userEmail)
            ->where('type', 'client')
            ->first();

        if ($existingClient) {
            return redirect()->away("http://localhost:5173/login_client?error=already_registered_as_client");
        }

        // Chercher transporteur existant
        $transporteur = Transporteur::where('email', $userEmail)
            ->where('type', 'transporteur')
            ->first();

        if ($transporteur) {
            // Mettre à jour l'adresse IP si différente
            if ($transporteur->adresse_ip !== $ip) {
                // Vérifier que la nouvelle IP n'est pas déjà utilisée par un autre transporteur
                $ipUsed = Transporteur::where('adresse_ip', $ip)
                    ->where('type', 'transporteur')
                    ->where('id', '!=', $transporteur->id)
                    ->exists();

                if ($ipUsed) {
                    $message = urlencode("Une vérification de sécurité empêche l’activation d’un nouvel essai gratuit. Veuillez contacter l’administrateur si vous pensez qu’il s’agit d’une erreur.");
                    return redirect()->away("http://localhost:5173/login_client?error=ip_already_used&message={$message}");
                }

                $transporteur->adresse_ip = $ip;
                $transporteur->save();
            }
        } else {
            // Nouveau transporteur, vérifier que IP n’est pas déjà utilisée
            $ipUsed = Transporteur::where('adresse_ip', $ip)
                ->where('type', 'transporteur')
                ->exists();

            if ($ipUsed) {
                $message = urlencode("Une vérification de sécurité empêche l’activation d’un nouvel essai gratuit. Veuillez contacter l’administrateur si vous pensez qu’il s’agit d’une erreur.");
                return redirect()->away("http://localhost:5173/login_client?error=ip_already_used&message={$message}");
            }

            // Créer un nouveau transporteur
            $transporteur = Transporteur::create([
                'email' => $userEmail,
                'nom' => $googleUser->getName(),
                'password' => bcrypt(Str::random(16)),
                'type' => 'transporteur',
                'statut_validation' => 'en_attente',
                'date_inscription' => now(),
                'abonnement_actif' => 'free_14_days',
                'email_verified_at' => now(),
                'adresse_ip' => $ip,
            ]);
        }

        // Générer token
        $token = $transporteur->createToken('authToken')->plainTextToken;
        

        return redirect()->away("http://localhost:5173/google-login-success?token={$token}");

    } catch (\Exception $e) {
        return redirect()->away("http://localhost:5173/login_client?error=google_exception");
    }
}



}
