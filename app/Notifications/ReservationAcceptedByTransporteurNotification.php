<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Carbon\Carbon;

class ReservationAcceptedByTransporteurNotification extends Notification
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
        $client = $this->reservation->client;

        return (new MailMessage)
            ->subject('Vous avez accepté une réservation 🚚')
            ->greeting('Bonjour ' . ($notifiable->nom ?? ''))
            ->line('Vous avez accepté la réservation du client : ' . ($client->nom ?? 'Client inconnu'))
            ->line('📦 Détails de la réservation :')
            ->line('Départ : ' . $this->reservation->adresse_depart . ', ' . $this->reservation->ville_depart)
            ->line('Arrivée : ' . $this->reservation->adresse_arrivee . ', ' . $this->reservation->ville_arrivee)
            ->line('Date : ' . Carbon::parse($this->reservation->date_heure)->format('d/m/Y H:i'))
            ->line('📞 Contact client :')
            ->line('Email : ' . ($client->email ?? 'non disponible'))
            ->line('Téléphone : ' . ($client->telephone ?? 'non disponible'))
            ->line('Merci d’utiliser notre plateforme R7il.');
    }

    public function toArray($notifiable)
    {
        return [
            'reservation_id' => $this->reservation->id,
            'client_id' => $this->reservation->client_id,
        ];
    }
}
