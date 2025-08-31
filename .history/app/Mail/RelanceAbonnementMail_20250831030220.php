<?php

namespace App\Mail;

use App\Models\Abonnement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RelanceAbonnementMail extends Mailable
{
    use Queueable, SerializesModels;

    public $abonnement;

    public function __construct(Abonnement $abonnement)
    {
        $this->abonnement = $abonnement;
    }

    public function build()
    {
        return $this->subject('Votre abonnement a expiré')
                    ->markdown('emails.relance')
                    ->with([
                        'nom' => $this->abonnement->transporteur->nom,
                        'type' => $this->abonnement->type,
                        'date_fin' => $this->abonnement->date_fin,
                    ]);
    }
}
