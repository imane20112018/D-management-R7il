<?php
namespace App\Http\Controllers\API;

use App\Models\Reservation;
use App\Models\Notification;
use App\Models\Transporteur;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Notifications\NewReservationNotification;

class ReservationController extends Controller
{
   public function store(Request $request)
{
    $validated = $request->validate([
        'client_id' => 'required|exists:transporteurs,id',
        'adresse_depart' => 'required|string',
        'ville_depart' => 'nullable|string',
        'adresse_arrivee' => 'required|string',
        'ville_arrivee' => 'nullable|string',
        'date_heure' => 'required|date',
        'etage' => 'required|integer|min:0',
        'ascenseur' => 'nullable|boolean',
        'surface' => 'nullable|numeric',
        'type_bien' => 'nullable|string',
        'details' => 'nullable|string',
    ]);

    $validated['statut'] = 'en_attente';

    $reservation = Reservation::create($validated);

    // Récupérer les transporteurs valides (exemple)
    $transporteurs = Transporteur::where('status', 'disponible')
        ->where('type', 'transporteur')
        ->whereNotNull('vehicule')
        ->whereNotNull('permis')
        ->whereNotNull('photo_vehicule')
        ->whereNotNull('carte_grise')
        ->where('statut_validation', 'valide')
        ->where('abonnement_actif', 'NOT LIKE', '%en_attente%')
        ->get();

    // Notifier tous les transporteurs valides
   foreach ($transporteurs as $transporteur) {
    $transporteur->notify(new NewReservationNotification($reservation));

    // récupérer la dernière notification créée
    $lastNotification = $transporteur->notifications()->latest()->first();

    if ($lastNotification) {
        $lastNotification->reservation_id = $reservation->id;
        $lastNotification->save();
    }
}
    return response()->json([
        'message' => 'Réservation créée avec succès.',
        'reservation' => $reservation,
    ], 201);
}



 // 📌 Modifier une réservation uniquement si statut = "en_attente"
    public function update(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        // 🔒 Bloquer la modification si statut différent
        if ($reservation->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Modification interdite. Cette réservation est déjà confirmée ou annulée.'
            ], 403);
        }

        $validated = $request->validate([
            'adresse_depart' => 'required|string',
            'adresse_arrivee' => 'required|string',
            'ville_depart' => 'required|string',
            'ville_arrivee' => 'required|string',
            'etage' => 'required|integer|min:0',
            'ascenseur' => 'required|nullable|boolean',
            'surface' => 'required|nullable|string',
            'type_bien' => 'required|nullable|string',
            'date_heure' => 'required|date',
            'details' => 'required|nullable|string',
        ]);

        $reservation->update($validated);

        return response()->json([
            'message' => 'Réservation mise à jour avec succès.',
            'reservation' => $reservation
        ]);
    }



public function hasActiveReservation($clientId)
{
    $exists = Reservation::where('client_id', $clientId)
        ->whereIn('statut', ['en_attente', 'acceptee']) // uniquement les "actives"
        ->exists();

    return response()->json([
        'hasReservation' => $exists,
    ]);
}


public function latest($id)
{
    $reservation = Reservation::where('client_id', $id)
        ->whereIn('statut', ['en_attente', 'acceptee']) // ignorer 'terminee'
        ->latest()
        ->first();

    if ($reservation) {
        return response()->json([
            'id' => $reservation->id,
            'statut' => $reservation->statut,
        ]);
    } else {
        return response()->json([
            'id' => null,
            'statut' => null,
        ]);
    }
}

public function marquerTerminee($id)
{
    $reservation = Reservation::findOrFail($id);

    // Vérifie que l'utilisateur connecté est bien le client
    if (auth('transporteur')->id() !== $reservation->client_id) {
        return response()->json(['message' => 'Non autorisé.'], 403);
    }

    // Vérifie que le statut actuel est 'acceptee'
    if ($reservation->statut !== 'acceptee') {
        return response()->json(['message' => 'Cette réservation ne peut pas être marquée comme terminée.'], 400);
    }

    // Mettre à jour le statut
    $reservation->statut = 'terminee';
    $reservation->save();

    return response()->json(['message' => 'Réservation marquée comme terminée.']);
}


// Dans ReservationController.php
public function listByClient()
{
    $user = auth()->user();

    if (! $user || ! \App\Models\Transporteur::find($user->id)) {
        return response()->json(['message' => 'Utilisateur non trouvé ou non autorisé.'], 403);
    }

    $reservations = Reservation::where('client_id', $user->id)
        ->orderByDesc('created_at')
        ->get();

    return response()->json($reservations);
}

public function show($id)
{
    $reservation = Reservation::findOrFail($id);

    // facultatif : vérifier que c’est bien le client concerné
    if (auth()->id() !== $reservation->client_id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    return response()->json($reservation);
}

public function destroy($id)
{
    $reservation = Reservation::findOrFail($id);

    // Optionnel : vérifier que la réservation est en statut 'en_attente' avant suppression
    if ($reservation->statut !== 'en_attente') {
        return response()->json([
            'message' => 'Impossible de supprimer une réservation acceptée ou terminée.'
        ], 403);
    }

    $reservation->delete();

    return response()->json([
        'message' => 'Réservation supprimée.'
    ]);
}





}
