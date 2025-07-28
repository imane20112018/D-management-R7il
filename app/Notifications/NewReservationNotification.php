<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewReservationNotification extends Notification
{
    use Queueable;

    protected $reservation;

    /**
     * Create a new notification instance.
     */
    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database'];  // On utilise la base de données pour stocker la notif
    }

    /**
     * Get the array representation of the notification (stockée en base).
     */
    public function toDatabase($notifiable)
    {
        return [
            'message' => "Nouvelle réservation créée par {$this->reservation->client->nom}",
            'reservation_id' => $this->reservation->id,
            // tu peux ajouter plus d’infos ici si besoin
        ];
    }
}
