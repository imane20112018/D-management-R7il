<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReservationAcceptedNotification extends Notification
{
    use Queueable;

    protected $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $transporteur = $this->reservation->transporteur;

        return (new MailMessage)
            ->subject('Votre réservation a été acceptée 🚚')
            ->greeting("Bonjour {$notifiable->nom},")
            ->line("Votre réservation du {$this->reservation->adresse_depart} à {$this->reservation->adresse_arrivee} a été acceptée par un transporteur.")
            ->line("Voici ses coordonnées pour le contacter :")
            ->line("Nom : {$transporteur->nom}")
            ->line("Email : {$transporteur->email}")
            ->line("Téléphone : {$transporteur->telephone}")
            ->line('Le transporteur vous contactera prochainement pour organiser le déménagement.')
            ->line('Merci d’avoir utilisé notre plateforme R7il.');
    }
}
