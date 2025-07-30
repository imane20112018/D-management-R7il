<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Reservation;
use App\Models\Transporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Notifications\ReservationAcceptedNotification;
use App\Notifications\ReservationAcceptedByTransporteurNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->unreadNotifications;
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
    }
    public function getReservationsFromNotifications(Request $request)
    {
        $user = $request->user();

        // Récupérer les IDs des réservations liées aux notifications
        $notificationReservationsIds = $user->notifications
            ->where('type', 'App\Notifications\NewReservationNotification')
            ->pluck('data.reservation_id')
            ->unique()
            ->toArray();

        // Charger les réservations avec la relation client
        $reservations = Reservation::with('client')
            ->whereIn('id', $notificationReservationsIds)
            ->get();

        return response()->json([
            'reservations' => $reservations
        ]);
    }

    public function show($id)
    {
        $reservation = Reservation::with('client')->findOrFail($id);

        // Optionnel : sécuriser que le transporteur est bien lié à cette réservation
        // if ($reservation->transporteur_id !== auth()->id()) {
        //     return response()->json(['message' => 'Non autorisé'], 403);
        // }

        return response()->json(['reservation' => $reservation]);
    }


    public function update(Request $request, $id)
    {
        $reservation = Reservation::with('client')->findOrFail($id);

        $request->validate([
            'statut' => 'required|in:en_attente,acceptee,terminee,annulee'
        ]);

        $user = auth()->user(); // transporteur connecté
        $ancienStatut = $reservation->statut;
        $nouveauStatut = $request->statut;

        // Bloquer modification si déjà terminé ou annulé
        if (in_array($ancienStatut, ['terminee', 'annulee'])) {
            return response()->json([
                'message' => 'Impossible de modifier : réservation déjà terminée ou annulée.'
            ], 403);
        }

        // Vérification des conflits horaires si on accepte la réservation
        if ($nouveauStatut === 'acceptee' && !$reservation->transporteur_id) {
            $dateReservation = Carbon::parse($reservation->date_heure);

            $hasConflict = Reservation::where('transporteur_id', $user->id)
                ->where('statut', 'acceptee')
                ->where('id', '!=', $reservation->id)
                ->get()
                ->contains(function ($r) use ($dateReservation) {
                    $diff = abs($dateReservation->diffInHours(Carbon::parse($r->date_heure)));
                    return $diff < 4; // moins de 4h d’écart
                });

            if ($hasConflict) {
                return response()->json([
                    'message' => 'Conflit horaire : vous avez déjà une réservation acceptée à moins de 4h.'
                ], 403);
            }

            // Associer le transporteur
            $reservation->transporteur_id = $user->id;
        }

        $reservation->statut = $nouveauStatut;
        $reservation->save();

        // Envoyer notifications après acceptation
        if ($nouveauStatut === 'acceptee' && $ancienStatut !== 'acceptee') {
            if ($reservation->client) {
                $reservation->client->notify(new ReservationAcceptedNotification($reservation));
            }
            if (!$reservation->client) {
                return response()->json(['message' => 'Client introuvable pour cette réservation.'], 404);
            }



            $user->notify(new ReservationAcceptedByTransporteurNotification($reservation));

            // Supprimer notifications des autres transporteurs
            DB::table('notifications')
                ->where('type', 'App\\Notifications\\NewReservationNotification')
                ->where('data->reservation_id', $reservation->id)
                ->delete();
        }

        // Si une réservation acceptée est annulée => réinitialiser
        if ($ancienStatut === 'acceptee' && $nouveauStatut === 'annulee') {
            $reservation->transporteur_id = null;
            $reservation->statut = 'en_attente';
            $reservation->save();

            $transporteurs = Transporteur::where('abonnement_actif', true)->get();

            foreach ($transporteurs as $transporteur) {
                $transporteur->notify(new \App\Notifications\NewReservationNotification($reservation));
            }
        }

        // Supprimer notification une fois terminée
        if ($nouveauStatut === 'terminee') {
            DB::table('notifications')
                ->where('type', 'App\\Notifications\\NewReservationNotification')
                ->where('data->reservation_id', $reservation->id)
                ->delete();
        }

        return response()->json(['message' => 'Statut mis à jour avec succès.']);
    }

    public function historiqueReservations(Request $request)
    {
        $transporteur = auth()->user();

        $reservations = Reservation::with('client')
            ->where('transporteur_id', $transporteur->id)
            ->whereIn('statut', ['acceptee', 'terminee', 'annulee'])
            ->latest()
            ->get();

        return response()->json(['reservations' => $reservations]);
    }
    public function update_statut(Request $request, $id)
    {
        $user = auth()->user();

        $request->validate([
            'statut' => 'required|in:acceptee,terminee,annulee',
        ]);

        $reservation = Reservation::where('id', $id)
            ->where('transporteur_id', $user->id)
            ->firstOrFail();

        // Si la réservation est déjà terminée ou annulée, on bloque la modification
        if (in_array($reservation->statut, ['terminee', 'annulee'])) {
            return response()->json([
                'message' => "Modification impossible : réservation déjà terminée ou annulée."
            ], 403);
        }

        $nouveauStatut = $request->input('statut');
        $reservation->statut = $nouveauStatut;

        // Si annulation, on dissocie le transporteur et on renotifie les transporteurs disponibles valides
        if ($nouveauStatut === 'annulee') {
            $reservation->statut = 'en_attente';
            $reservation->transporteur_id = null;
            $reservation->save();

            $transporteurs = Transporteur::where('status', 'disponible')
                ->where('type', 'transporteur')
                ->whereNotNull('vehicule')
                ->whereNotNull('permis')
                ->whereNotNull('photo_vehicule')
                ->whereNotNull('carte_grise')
                ->where('statut_validation', 'valide')
                ->where('abonnement_actif', 'NOT LIKE', '%en_attente%')
                ->get();

            foreach ($transporteurs as $transporteur) {
                $transporteur->notify(new \App\Notifications\NewReservationNotification($reservation));

                // Mettre à jour la notification avec l'id de réservation si nécessaire
                $lastNotification = $transporteur->notifications()->latest()->first();
                if ($lastNotification) {
                    $lastNotification->reservation_id = $reservation->id;
                    $lastNotification->save();
                }
            }

            return response()->json(['message' => 'Statut mis à jour et notifications relancées']);
        }

        // Sinon on sauvegarde simplement la mise à jour
        $reservation->save();

        // Si accepté, on notifie le client (optionnel)
        if ($nouveauStatut === 'acceptee') {
            $reservation->client->notify(new \App\Notifications\ReservationAcceptedNotification($reservation));
        }

        return response()->json(['message' => 'Statut mis à jour avec succès']);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();

        // Récupérer toutes les notifications NewReservationNotification de cet utilisateur
        $notifications = $user->notifications()
            ->where('type', 'App\Notifications\NewReservationNotification')
            ->get()
            ->filter(function ($notification) use ($id) {
                return isset($notification->data['reservation_id']) && $notification->data['reservation_id'] == $id;
            });

        // Supprimer toutes ces notifications correspondantes
        foreach ($notifications as $notification) {
            $notification->delete();
        }

        return response()->json(['message' => 'Notification(s) supprimée(s) avec succès']);
    }
}
