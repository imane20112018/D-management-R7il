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
        $validated = $request->validate([
            'type' => 'required|in:free_14_days,pack_1_month,pack_6_months,pack_1_year',
        ]);

        $transporteur = Auth::user();


        if (!$transporteur || $transporteur->type !== 'transporteur') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifie si un abonnement actif existe
        $existeActif = Abonnement::where('transporteur_id', $transporteur->id)
            ->where('statut', 'valide')
            ->whereDate('date_fin', '>=', now())
            ->exists();

        if ($existeActif) {
            return response()->json(['message' => 'Un abonnement actif existe déjà.'], 422);
        }

        $abonnement = Abonnement::create([
            'transporteur_id' => $transporteur->id,
            'type'            => $validated['type'],
            'statut'          => 'en_attente',
        ]);

        $transporteur->update([
            'abonnement_actif' => 'en_attente',
        ]);

        return response()->json([
            'message'    => '✅ Demande envoyée à l’administrateur.',
            'abonnement' => $abonnement,
        ], 201);
    }

    // ✅ Admin — liste des demandes en attente
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $demandes = Abonnement::with(['transporteur:id,nom,email,telephone'])
            ->enAttente()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($demandes);
    }

    // ✅ Admin — valider une demande
    public function valider($id)
    {
        $abonnement = Abonnement::with('transporteur')->findOrFail($id);

        if ($abonnement->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande n’est pas en attente.'], 422);
        }

        DB::transaction(function () use ($abonnement) {
            $dateDebut = now();
            $dateFin = $this->computeEndDate($dateDebut, $abonnement->type);

            // 1) Abonnement validé
            $abonnement->update([
                'statut'      => 'valide',
                'date_debut'  => $dateDebut->toDateString(),
                'date_fin'    => $dateFin->toDateString(),
            ]);

            // 2) Mettre à jour le transporteur
            $abonnement->transporteur->update([
                'abonnement_actif' => $abonnement->type,     // ex: pack_1_month
                'date_fin_essai'   => $dateFin->toDateString(), // réutilisé comme "date fin pack"
            ]);
        });

        return response()->json(['message' => 'Abonnement validé ✅']);
    }

    // ✅ Admin — refuser une demande
    public function refuser($id)
    {
        $abonnement = Abonnement::with('transporteur')->findOrFail($id);

        if ($abonnement->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande n’est pas en attente.'], 422);
        }

        $abonnement->update(['statut' => 'refuse']);

        // On remet le flag "en_attente" sur le transporteur seulement s’il n’a rien d’actif
        $hasActive = Abonnement::where('transporteur_id', $abonnement->transporteur_id)
            ->where('statut', 'valide')
            ->whereDate('date_fin', '>=', now())
            ->exists();

        if (!$hasActive) {
            $abonnement->transporteur->update(['abonnement_actif' => 'en_attente']);
        }

        return response()->json(['message' => 'Demande refusée ❌']);
    }

    // 🔧 utilitaire pour calculer la date de fin
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

public function statut(Request $request)
{
    $transporteur = $request->user();

    // abonnement actif (champ dans transporteur)
    $abonnementActif = $transporteur->abonnement_actif;
    $statutValidation = $transporteur->statut_validation;

    // dernière demande d’abonnement dans la table abonnements
    $demande = \App\Models\Abonnement::where('transporteur_id', $transporteur->id)
                ->latest() // prend la plus récente
                ->first();

    return response()->json([
        'abonnement_actif' => $abonnementActif,
        'statut_validation' => $statutValidation,
        'type_demande' => $demande ? $demande->type : null,
        'statut_demande' => $demande ? $demande->statut : null,
    ]);
}


 public function checkout(Request $request)
    {
        $request->validate([
            'type' => 'required|in:free_14_days,pack_1_month,pack_6_months,pack_1_year',
        ]);

        $transporteur = $request->user();

        // 🎯 définir prix en centimes (exemple)
        $prixPacks = [
            'free_14_days' => 0,
            'pack_1_month' => 1000,   // 10€
            'pack_6_months' => 5000,  // 50€
            'pack_1_year' => 9000,    // 90€
        ];
        $amount = $prixPacks[$request->type];

        if ($amount === 0) {
            // Pack gratuit → créer direct en attente
            $abonnement = Abonnement::create([
                'transporteur_id' => $transporteur->id,
                'type' => $request->type,
                'statut' => 'en_attente',
            ]);

            return response()->json([
                'message' => 'Abonnement essai créé',
                'abonnement' => $abonnement,
            ]);
        }

        // Stripe config
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $session = CheckoutSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur', // ou mad si disponible
                    'product_data' => [
                        'name' => ucfirst(str_replace('_',' ', $request->type)),
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => env('FRONTEND_URL') . '/abonnement/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => env('FRONTEND_URL') . '/abonnement/cancel',
            'metadata' => [
                'transporteur_id' => $transporteur->id,
                'type' => $request->type,
            ],
        ]);

        return response()->json([
            'id' => $session->id,
            'url' => $session->url,
        ]);
    }
// ✅ Liste des abonnements valides
public function valide()
{
    return Abonnement::with('transporteur')
        ->where('statut', 'valide')
        ->orderBy('date_fin', 'asc')
        ->paginate(10);
}


// ✅ Résiliation forcée
public function resilier($id)
{
    $abonnement = Abonnement::with('transporteur')->findOrFail($id);

    if ($abonnement->statut !== 'valide') {
        return response()->json(['message' => 'Abonnement non valide.'], 422);
    }

    // Forcer expiration
    $abonnement->update([
        'statut' => 'expire',
        'date_fin' => now()->toDateString()
    ]);

    // Transporteur retourne en attente
    $abonnement->transporteur->update([
        'abonnement_actif' => 'en_attente'
    ]);

    return response()->json(['message' => 'Abonnement résilié ❌']);
}
public function refuses()
{
    $abonnements = Abonnement::with('transporteur') // <-- très important
        ->where('statut', 'refuse')
        ->paginate(10);

    return response()->json($abonnements);
}

public function destroy($id)
{
    $abonnement = \App\Models\Abonnement::findOrFail($id);

    if ($abonnement->statut !== 'refuse') {
        return response()->json(['message' => 'Seuls les abonnements refusés peuvent être supprimés définitivement'], 403);
    }

    $abonnement->delete();

    return response()->json(['message' => 'Abonnement supprimé définitivement']);
}


}
