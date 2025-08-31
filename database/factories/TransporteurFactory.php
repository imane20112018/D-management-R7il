<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class TransporteurFactory extends Factory
{
    public function definition(): array
    {
        $statuts = ['en_attente', 'valide', 'refuse'];
        $abonnements = ['en_attente','free_14_days', 'pack_1_month', 'pack_6_months', 'pack_1_year'];

        // ✅ Listes de photos
        $photosProfil = [
            'transporteurs_images/profil1.png',
            'transporteurs_images/profil2.png',
            'transporteurs_images/profil3.png',
        ];

        $vehicules = [
            'transporteurs_images/vehicule1.png',
            'transporteurs_images/vehicule2.png',
            'transporteurs_images/vehicule3.png',
        ];

        $permisImages = [
            'transporteurs_images/permis1.png',
            'transporteurs_images/permis2.png',
            'transporteurs_images/permis3.png',
        ];

        $cartesGrises = [
            'transporteurs_images/carte1.png',
            'transporteurs_images/carte2.png',
            'transporteurs_images/carte3.png',
        ];

        return [
            'nom' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'type' => 'transporteur',

            'vehicule' => $this->faker->randomElement(['Camion', 'camionnettes', 'utilitaires']),

            // ✅ Choix aléatoire d'image (ou null dans certains cas)
            'permis' => $this->faker->boolean(80) ? $this->faker->randomElement($permisImages) : null,
            'photo_vehicule' => $this->faker->boolean(70) ? $this->faker->randomElement($vehicules) : null,
            'carte_grise' => $this->faker->boolean(70) ? $this->faker->randomElement($cartesGrises) : null,
            'photo_profil' => $this->faker->boolean(70) ? $this->faker->randomElement($photosProfil) : null,

            'statut_validation' => $this->faker->randomElement($statuts),
            'abonnement_actif' => $this->faker->randomElement($abonnements),

            'date_inscription' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'date_fin_essai' => $this->faker->dateTimeBetween('now', '+1 year'),

            'email_verified_at' => $this->faker->boolean(80) ? now() : null,
            'adresse' => $this->faker->address(),
            'telephone' => $this->faker->phoneNumber(),

            'status' => $this->faker->randomElement(['disponible', 'indisponible']),
            'adresse_ip' => $this->faker->ipv4(),

            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
