<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Abonnement;
use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;

class AbonnementController extends Controller
{
    // ✅ Transporteur : envoyer une demande sans paiement
    public function demande(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:free_14_days,pack_1_month,pack_6_months,pack_1_year',
        ]);

        $transporteur = Auth::user();

        if (!$transporteur || $transporteur->type !== 'transporteur') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifie s’il existe déjà un abonnement actif
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

        $transporteur->update(['abonnement_actif' => 'en_attente']);

        return response()->json([
            'message'    => '✅ Demande envoyée à l’administrateur.',
            'abonnement' => $abonnement,
        ], 201);
    }

    // ✅ Admin : liste des demandes en attente
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $demandes = Abonnement::with(['transporteur:id,nom,email,telephone'])
            ->where('statut', 'en_attente')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($demandes);
    }

    // ✅ Admin : valider une demande
    public function valider($id)
    {
        $abonnement = Abonnement::with('transporteur')->findOrFail($id);

        if ($abonnement->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande n’est pas en attente.'], 422);
        }

        DB::transaction(function () use ($abonnement) {
            $dateDebut = now();
            $dateFin   = $this->computeEndDate($dateDebut, $abonnement->type);

            $abonnement->update([
                'statut'      => 'valide',
                'date_debut'  => $dateDebut->toDateString(),
                'date_fin'    => $dateFin->toDateString(),
            ]);

            $abonnement->transporteur->update([
                'abonnement_actif' => $abonnement->type,
                'date_fin_essai'   => $dateFin->toDateString(),
            ]);
        });

        return response()->json(['message' => 'Abonnement validé ✅']);
    }

    // ✅ Admin : refuser une demande
    public function refuser($id)
    {
        $abonnement = Abonnement::with('transporteur')->findOrFail($id);

        if ($abonnement->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande n’est pas en attente.'], 422);
        }

        $abonnement->update(['statut' => 'refuse']);

        $hasActive = Abonnement::where('transporteur_id', $abonnement->transporteur_id)
            ->where('statut', 'valide')
            ->whereDate('date_fin', '>=', now())
            ->exists();

        if (!$hasActive) {
            $abonnement->transporteur->update(['abonnement_actif' => 'en_attente']);
        }

        return response()->json(['message' => 'Demande refusée ❌']);
    }

    // ✅ Transporteur : voir statut actuel
    public function statut(Request $request)
    {
        $transporteur = $request->user();

        $demande = Abonnement::where('transporteur_id', $transporteur->id)
            ->latest()
            ->first();

        return response()->json([
            'abonnement_actif'  => $transporteur->abonnement_actif,
            'statut_validation' => $transporteur->statut_validation,
            'type_demande'      => $demande ? $demande->type : null,
            'statut_demande'    => $demande ? $demande->statut : null,
        ]);
    }

    // ✅ Transporteur : checkout Stripe
    public function checkout(Request $request)
    {
        $request->validate([
            'type' => 'required|in:free_14_days,pack_1_month,pack_6_months,pack_1_year',
        ]);

        $transporteur = $request->user();

        $prixPacks = [
            'free_14_days' => 0,
            'pack_1_month' => 1000,
            'pack_6_months' => 5000,
            'pack_1_year' => 9000,
        ];
        $amount = $prixPacks[$request->type];

        if ($amount === 0) {
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

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $session = CheckoutSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur', // ou mad si dispo
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

    // 🔧 utilitaire
    private function computeEndDate($start, $type)
    {
        $end = $start->copy();
        switch ($type) {
            case 'pack_1_month': $end->addMonth(); break;
            case 'pack_6_months': $end->addMonths(6); break;
            case 'pack_1_year': $end->addYear(); break;
            case 'free_14_days': $end->addDays(14); break;
        }
        return $end;
    }
}
