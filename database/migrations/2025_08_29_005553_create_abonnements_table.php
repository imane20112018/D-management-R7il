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
      Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transporteur_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['free_14_days','pack_1_month','pack_6_months','pack_1_year']);
            $table->enum('statut', ['en_attente','valide','refuse','expire'])->default('en_attente');

            // dates de période d’abonnement (remplies au moment de l’acceptation)
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();

            // (facultatif) infos paiement si tu ajoutes Stripe plus tard
            $table->string('reference_paiement')->nullable();
            $table->integer('montant')->nullable(); // en centimes ou en DH

            $table->timestamps();

            $table->index(['transporteur_id', 'statut']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};
