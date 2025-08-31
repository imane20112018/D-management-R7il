<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Transporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AbonnementController extends Controller
{
    // ✅ Transporteur — créer une demande d’abonnement
    public function demande(Request $request)
    {
        $validated = $request->validate(['type' => 'required|in:free_14_days,pack_1_month,pack_6_months,pack_1_year',]);
        $transporteur = Auth::user();
        dd(Auth::user());
        if (!$transporteur || $transporteur->type !== 'transporteur') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        // Vérifie si un abonnement actif existe
        $existeActif = Abonnement::where('transporteur_id', $transporteur->id)->where('statut', 'valide')->whereDate('date_fin', '>=', now())->exists();
        if ($existeActif) {
            return response()->json(['message' => 'Un abonnement actif existe déjà.'], 422);
        }
        $abonnement = Abonnement::create(['transporteur_id' => $transporteur->id, 'type' => $validated['type'], 'statut' => 'en_attente',]);
        $transporteur->update(['abonnement_actif' => 'en_attente',]);
        return response()->json(['message' => '✅ Demande envoyée à l’administrateur.', 'abonnement' => $abonnement,], 201);
    }
    // ✅ Admin — liste des demandes en attente 
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $demandes = Abonnement::with(['transporteur:id,nom,email,telephone'])->enAttente()->orderByDesc('created_at')->paginate($perPage);
        return response()->json($demandes);
    } // ✅ Admin — valider une demande 
    public function valider($id)
    {
        $abonnement = Abonnement::with('transporteur')->findOrFail($id);
        if ($abonnement->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande n’est pas en attente.'], 422);
        }
        DB::transaction(function () use ($abonnement) {
            $dateDebut = now();
            $dateFin = $this->computeEndDate($dateDebut, $abonnement->type); // 1) Abonnement validé 
            $abonnement->update(['statut' => 'valide', 'date_debut' => $dateDebut->toDateString(), 'date_fin' => $dateFin->toDateString(),]); // 2) Mettre à jour le transporteur
            $abonnement->transporteur->update([
                'abonnement_actif' => $abonnement->type, // ex: pack_1_month 
                'date_fin_essai' => $dateFin->toDateString(), // réutilisé comme "date fin pack"
            ]);
        });
        return response()->json(['message' => 'Abonnement validé ✅']);
    } // ✅ Admin — refuser une demande 
    public function refuser($id)
    {
        $abonnement = Abonnement::with('transporteur')->findOrFail($id);
        if ($abonnement->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande n’est pas en attente.'], 422);
        }
        $abonnement->update(['statut' => 'refuse']); // On remet le flag "en_attente" sur le transporteur seulement s’il n’a rien d’actif 
        $hasActive = Abonnement::where('transporteur_id', $abonnement->transporteur_id)->where('statut', 'valide')->whereDate('date_fin', '>=', now())->exists();
        if (!$hasActive) {
            $abonnement->transporteur->update(['abonnement_actif' => 'en_attente']);
        }
        return response()->json(['message' => 'Demande refusée ❌']);
    } // 🔧 utilitaire pour calculer la date de fin
    private function computeEndDate($start, $type)
    {
        $end = $start->copy();
        switch ($type) {
            case 'pack_1_month':
                $end->addMonth();
                break;
            case 'pack_6_months':
                $end->addMonths(6);
                break;
            case 'pack_1_year':
                $end->addYear();
                break;
            case 'free_14_days':
                $end->addDays(14);
                break;
            default:
                $end->addDays(0);
        }
        return $end;
    }
}
