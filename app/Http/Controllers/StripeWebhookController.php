<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use App\Models\Abonnement;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            // Récup info metadata
            $transporteurId = $session->metadata->transporteur_id;
            $type = $session->metadata->type;

            // Dates début/fin
            $dateDebut = now();
            $dateFin = match($type) {
                'pack_1_month' => now()->addMonth(),
                'pack_6_months' => now()->addMonths(6),
                'pack_1_year' => now()->addYear(),
                default => now()->addDays(14),
            };

            Abonnement::create([
                'transporteur_id' => $transporteurId,
                'type' => $type,
                'statut' => 'valide',
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'reference_paiement' => $session->payment_intent,
                'montant' => $session->amount_total,
            ]);
        }

        return response()->json(['status' => 'success']);
    }
}
