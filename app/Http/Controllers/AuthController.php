<?php
namespace App\Http\Controllers;

use App\Models\Transporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:transporteurs,email',
            'password' => 'required|string|min:6|confirmed',
            'type' => 'required|in:client,transporteur',
        ]);

        $user = Transporteur::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => $request->type,
            'date_inscription' => now(),
        ]);
        $user->sendEmailVerificationNotification(); // ✉️ Envoie le mail

        return response()->json($user, 201);
    }

 public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    $user = Transporteur::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Identifiants incorrects'], 401);
    }
 if (is_null($user->email_verified_at)) {
        return response()->json(['message' => 'Vous devez d’abord activer votre compte par e-mail.'], 403);
    }

    $token = $user->createToken('transporteur_token')->plainTextToken;

    return response()->json([
        'message' => 'Connexion réussie',
        'token' => $token,
        'user' => $user
    ]);
}

   public function logout(Request $request)
{
    // Révoque seulement le token actuellement utilisé
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Déconnecté avec succès.']);
}

}
