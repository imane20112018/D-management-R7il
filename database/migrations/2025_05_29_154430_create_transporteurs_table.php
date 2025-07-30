<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transporteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('email')->unique();
            $table->string('password');

            $table->enum('type', ['client', 'transporteur'])->default('client');

            // Champs spécifiques au transporteur (optionnels pour un client)
            $table->string('vehicule', 100)->nullable();
            $table->string('permis', 100)->nullable();
            $table->string('photo_vehicule', 255)->nullable();
            $table->string('carte_grise', 255)->nullable();
            $table->enum('statut_validation', ['en_attente', 'valide', 'refuse'])->default('en_attente');
           $table->enum('abonnement_actif', ['en_attente','free_14_days', 'pack_1_month', 'pack_6_months', 'pack_1_year'])->nullable();
            $table->date('date_inscription')->nullable();
            $table->date('date_fin_essai')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporteurs');
    }
};
