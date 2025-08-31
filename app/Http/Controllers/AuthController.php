<?php

namespace App\Http\Controllers;

use App\Models\Transporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\TransporteurProfilComplet;
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

        $ip = $request->ip();

        // Vérifie si un compte avec abonnement 'free_14_days' existe déjà avec cette IP
        $existe = Transporteur::where('adresse_ip', $ip)
            ->where('abonnement_actif', 'free_14_days')
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Une vérification de sécurité empêche l’activation d’un nouvel essai gratuit. Veuillez contacter l’administrateur si vous pensez qu’il s’agit d’une erreur.'
            ], 403);
        }

        $user = Transporteur::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => $request->type,
            'date_inscription' => now(),
            'abonnement_actif' => 'free_14_days',
            'adresse_ip' => $ip,
            'statut_validation' => 'en_attente',
            'email_verified_at' => null,  // Pas encore vérifié
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Inscription réussie. Veuillez vérifier votre email pour activer votre compte.',
            'user' => $user
        ], 201);
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

        $oldType = $user->type;

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:transporteurs,email,' . $user->id,
            'vehicule' => 'nullable|string',
            'adresse' => 'nullable|string',
            'telephone' => 'nullable|string',
            'type' => 'nullable|string|in:client,transporteur',
        ]);

        // Nettoyer les chaînes "null" reçues et les transformer en NULL réel
        foreach ($validated as $key => $value) {
            if (is_string($value) && strtolower($value) === 'null') {
                $validated[$key] = null;
            }
        }

        $user->fill($validated);

        // Création dossier
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

        // $typeChanged = $oldType !== $user->type;


        // ✅ Vérifier si profil complet
        $isComplete = $user->vehicule && $user->adresse && $user->telephone &&
            $user->photo_vehicule && $user->permis && $user->carte_grise;

        if ($isComplete && $user->statut_validation === 'en_attente') {
            Mail::to("admin@example.com")->send(new TransporteurProfilComplet($user));
        }

        return response()->json([
            'message' => '✅ Profil mis à jour avec succès.',
            'user' => $user,
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
            'id',
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
    public function show($id)
    {
        $client = Transporteur::findOrFail($id);
        return response()->json($client);
    }
public function getTransporteursIncomplets()
{
    $query = Transporteur::where('type', 'transporteur')
        ->where(function ($query) {
            $query->whereNull('nom')
                  ->orWhereRaw("TRIM(nom) = ''")
                  ->orWhereNull('email')
                  ->orWhereRaw("TRIM(email) = ''")
                  ->orWhereNull('vehicule')
                  ->orWhereRaw("TRIM(vehicule) = ''")
                  ->orWhereNull('permis')
                  ->orWhereRaw("TRIM(permis) = ''")
                  ->orWhereNull('photo_vehicule')
                  ->orWhereNull('carte_grise');
        });

    // ⚡️ Pagination (10 par page)
    $transporteurs = $query->paginate(10, [
        'id',
        'nom',
        'email',
        'vehicule',
        'permis',
        'photo_vehicule',
        'carte_grise',
        'statut_validation',
        'abonnement_actif',
        'date_inscription',
        'date_fin_essai',
    ]);

    // ➕ Ajouter missing_fields à chaque transporteur
    $transporteurs->getCollection()->transform(function ($t) {
        $missing = [];
        if (!$t->nom || trim($t->nom) === '') $missing[] = 'Nom';
        if (!$t->email || trim($t->email) === '') $missing[] = 'Email';
        if (!$t->vehicule || trim($t->vehicule) === '') $missing[] = 'Véhicule';
        if (!$t->permis || trim($t->permis) === '') $missing[] = 'Permis';
        if (!$t->photo_vehicule) $missing[] = 'Photo véhicule';
        if (!$t->carte_grise) $missing[] = 'Carte grise';

        $t->missing_fields = $missing;
        return $t;
    });

    return response()->json($transporteurs);
}

public function deleteTransporteurIncomplet($id)
{
    $transporteur = Transporteur::find($id);

    if (!$transporteur) {
        return response()->json(['message' => '❌ Transporteur introuvable'], 404);
    }

    $transporteur->delete();

    return response()->json(['message' => '✅ Transporteur supprimé avec succès']);
}
public function getTransporteursValides()
{
    $transporteurs = Transporteur::where('type', 'transporteur')
        ->where('statut_validation', 'valide')
        ->paginate(10, [ // 👈 ajoute paginate avec 10 résultats par page
            'id',
            'nom',
            'email',
            'telephone',
            'vehicule',
            'permis',
            'photo_profil',
            'photo_vehicule',
            'carte_grise',
            'statut_validation',
            'abonnement_actif',
            'date_inscription',
            'date_fin_essai',
        ]);

    return response()->json($transporteurs);
}
public function getTransporteursEnAttente()
{
    $transporteurs = Transporteur::where('type', 'transporteur')
        ->where('statut_validation', 'en_attente')
        ->whereNotNull('nom')
        ->whereRaw("TRIM(nom) != ''")
        ->whereNotNull('email')
        ->whereRaw("TRIM(email) != ''")
        ->whereNotNull('vehicule')
        ->whereRaw("TRIM(vehicule) != ''")
        ->whereNotNull('permis')
        ->whereRaw("TRIM(permis) != ''")
        ->whereNotNull('photo_vehicule')
        ->whereNotNull('carte_grise')
        ->paginate(10, [
            'id',
            'nom',
            'email',
            'telephone',
            'photo_profil',
            'vehicule',
            'permis',
            'photo_vehicule',
            'carte_grise',
            'statut_validation',
            'abonnement_actif',
            'date_inscription',
            'date_fin_essai',
        ]);

    return response()->json($transporteurs);
}

public function me(Request $request)
{
    return response()->json($request->user());
}
// détails d’un transporteur
public function showTransporteur($id)
{
    return Transporteur::findOrFail($id);
}

public function updateStatut(Request $request, $id)
{
    $request->validate([
        'statut_validation' => 'required|in:en_attente,valide,refuse'
    ]);

    $t = Transporteur::findOrFail($id);
    $t->statut_validation = $request->statut_validation;
    $t->save();

    return response()->json($t);
}
public function getTransporteurById($id)
{
    $transporteur = Transporteur::findOrFail($id);
    return response()->json($transporteur);
}
public function validerTransporteur($id)
{
    $t = Transporteur::findOrFail($id);
    $t->statut_validation = 'valide';
    $t->save();

    return response()->json(['message' => 'Transporteur validé avec succès']);
}

public function refuserTransporteur($id)
{
    $t = Transporteur::findOrFail($id);
    $t->statut_validation = 'refuse';
    $t->save();

    return response()->json(['message' => 'Transporteur refusé avec succès']);
}
public function refuses(Request $request)
{
    // Nombre d'éléments par page (10 par défaut, mais peut être changé via ?per_page=15)
    $perPage = $request->get('per_page', 10);

    $transporteurs = Transporteur::where('statut_validation', 'refuse')
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

    return response()->json($transporteurs);
}

public function remettreEnAttente($id)
{
    $transporteur = Transporteur::find($id);

    if (!$transporteur) {
        return response()->json(['message' => 'Transporteur introuvable'], 404);
    }

    $transporteur->statut_validation = 'en_attente';
    $transporteur->save();

    return response()->json(['message' => 'Transporteur remis en attente ⏳']);
}


}