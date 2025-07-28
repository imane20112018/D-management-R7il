<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;

class Transporteur extends Authenticatable implements MustVerifyEmail
{
use HasApiTokens, HasFactory, Notifiable, CanResetPassword;
    protected $table = 'transporteurs';

    protected $fillable = [
        'nom',
        'email',
        'password',
        'type',
        'vehicule',
        'permis',
        'photo_vehicule',
        'photo_profil',
        'carte_grise',
        'statut_validation',
        'date_inscription',
        'date_fin_essai',
        'abonnement_actif',
        'email_verified_at',
        'adresse',
        'telephone',
        'status'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'abonnement_actif' => 'boolean',
        'date_inscription' => 'date',
        'date_fin_essai' => 'date',
        'email_verified_at' => 'datetime',

    ];
public function getEmailForVerification()
{
    return $this->email;
}
 public function reservationsClient()
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    /**
     * Réservations acceptées par ce transporteur
     */
    public function reservationsTransporteur()
    {
        return $this->hasMany(Reservation::class, 'transporteur_id');
    }

}
