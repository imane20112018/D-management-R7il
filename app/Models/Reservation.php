<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'client_id',
        'transporteur_id',
        'adresse_depart',
        'adresse_arrivee',
        'ville_depart',
        'ville_arrivee',
        'etage',
        'ascenseur',
        'surface',
        'type_bien',
        'date_heure',
        'details',
        'statut',
    ];

    protected $table = 'reservations';

    /**
     * Client ayant créé la réservation.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Transporteur::class, 'client_id');
    }

    /**
     * Transporteur qui accepte la réservation.
     */
    public function transporteur(): BelongsTo
    {
        return $this->belongsTo(Transporteur::class, 'transporteur_id');
    }
}
