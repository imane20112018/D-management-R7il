<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Abonnement extends Model
{
    use HasFactory;

    protected $fillable = [
        'transporteur_id',
        'type',
        'statut',
        'date_debut',
        'date_fin',
        'reference_paiement',
        'montant',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function transporteur()
    {
        return $this->belongsTo(Transporteur::class);
    }

    // Scopes utiles
    public function scopeEnAttente($q) { return $q->where('statut', 'en_attente'); }
    public function scopeActifs($q) { return $q->where('statut', 'valide')->whereDate('date_fin', '>=', now()); }
}
