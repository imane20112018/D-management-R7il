<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Transporteur extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'transporteurs';

    protected $fillable = [
        'nom',
        'email',
        'password',
        'type',
        'vehicule',
        'permis',
        'photo_vehicule',
        'carte_grise',
        'statut_validation',
        'date_inscription',
        'date_fin_essai',
        'abonnement_actif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'abonnement_actif' => 'boolean',
        'date_inscription' => 'date',
        'date_fin_essai' => 'date',
    ];
}
