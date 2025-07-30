<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

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
         return new DatabaseMessage([
        'reservation_id' => $this->reservation->id,
        'type' => 'new_reservation',
        'adresse_depart' => $this->reservation->adresse_depart,
        'message' => "Nouvelle réservation créée par {$this->reservation->client->nom}",
        'client_photo' => $this->reservation->client->photo_profil,  // chemin photo client, ex: 'storage/photos/xxx.jpg'
        // autre infos utiles
    ]);
    }
}
