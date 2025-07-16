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

    public function updateProfil(Request $request)
    {
        $user = $request->user();

        $oldType = $user->type; // ✅ récupère le type actuel dans la base

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:transporteurs,email,' . $user->id,
            'vehicule' => 'nullable|string',
            'adresse' => 'nullable|string',
            'telephone' => 'nullable|string',
            'type' => 'nullable|string|in:client,transporteur,pro',
            'facebook' => 'nullable|string',
            'alternative_phone' => 'nullable|string',
        ]);

        $user->fill($validated);

        // 🔐 Crée le dossier avec nom_id
        $folderName = strtolower(str_replace(' ', '_', $user->nom . '_' . $user->id));
        $uploadPath = public_path("transporteurs_images/{$folderName}");

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $handleFile = function ($fieldName, $fileLabel) use ($request, $user, $uploadPath, $folderName) {
            if ($request->hasFile($fieldName)) {
                if ($user->$fieldName && file_exists(public_path($user->$fieldName))) {
                    unlink(public_path($user->$fieldName));
                }

                $file = $request->file($fieldName);
                $filename = $fileLabel . '.' . $file->getClientOriginalExtension();
                $file->move($uploadPath, $filename);
                $user->$fieldName = "transporteurs_images/{$folderName}/{$filename}";
            }
        };

        $handleFile('photo_vehicule', 'photo_vehicule');
        $handleFile('carte_grise', 'carte_grise');
        $handleFile('photo_profil', 'photo_profil');
        $handleFile('permis', 'permis');

        $user->save();

        $typeChanged = $oldType !== $user->type; // ✅ comparaison avec la base

        return response()->json([
            'message' => '✅ Profil mis à jour avec succès.',
            'user' => $user,
            'type_changed' => $typeChanged // ⚠️ envoyé au frontend
        ]);
    }



    public function updateStatus(Request $request)
    {
        $user = $request->user(); // ✅ Ceci fonctionne avec Sanctum

        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $request->validate([
            'status' => 'required|in:disponible,indisponible'
        ]);

        $user->status = $request->status;
        $user->save();

        return response()->json(['message' => '✅ Statut mis à jour avec succès.']);
    }
    public function getClients()
    {
        $clients = Transporteur::where('type', 'client')->get([
            'nom',
            'email',
            'statut_validation',
            'date_inscription',
            'adresse',
            'telephone',
            'photo_profil',
            'status',
        ]);

        return response()->json($clients);
    }
}
