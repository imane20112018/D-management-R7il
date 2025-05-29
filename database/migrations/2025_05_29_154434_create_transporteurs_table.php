<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transporteurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('vehicule');
            $table->string('photo_vehicule');
            $table->string('permis');
            $table->string('carte_grise');
            $table->enum('statut_validation', ['en_attente', 'valide', 'refuse'])->default('en_attente');
            $table->date('date_inscription');
            $table->date('date_fin_essai');
            $table->boolean('abonnement_actif')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transporteurs');
    }
};
