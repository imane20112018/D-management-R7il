<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    protected $fillable = [
        'id',
        'type',
        'notifiable_id',
        'notifiable_type',
        'data',
        'read_at',
        'created_at',
        'updated_at',
        'reservation_id',  // ajoute ici
    ];
}
