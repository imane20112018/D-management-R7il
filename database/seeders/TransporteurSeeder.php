<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transporteur;

class TransporteurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
{
    // ✅ 50 transporteurs totalement aléatoires
    Transporteur::factory()->count(50)->create();

    // ✅ 10 transporteurs en attente
    Transporteur::factory()->count(10)->create([
        'statut_validation' => 'en_attente',
    ]);

    // ✅ 10 transporteurs validés
    Transporteur::factory()->count(10)->create([
        'statut_validation' => 'valide',
    ]);

    // ✅ 10 transporteurs refusés
    Transporteur::factory()->count(10)->create([
        'statut_validation' => 'refuse',
    ]);
}
}
