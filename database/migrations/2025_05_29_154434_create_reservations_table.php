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
      Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // 🔗 Relations
            $table->foreignId('client_id')->constrained('transporteurs')->onDelete('cascade');
            $table->foreignId('transporteur_id')->nullable()->constrained('transporteurs')->onDelete('set null');

            // 📍 Informations de lieu
            $table->string('adresse_depart');
            $table->string('ville_depart')->nullable();
            $table->string('adresse_arrivee');
            $table->string('ville_arrivee')->nullable();

            // 🏢 Détails logistiques
            $table->integer('etage')->nullable();
            $table->boolean('ascenseur')->nullable();
            $table->float('surface')->nullable();
            $table->string('type_bien')->nullable(); // Appartement, maison...

            // 🕒 Date et autres infos
            $table->datetime('date_heure');
            $table->text('details')->nullable();

            // 📌 Statut
$table->enum('statut', ['en_attente', 'acceptee', 'terminee'])->default('en_attente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
