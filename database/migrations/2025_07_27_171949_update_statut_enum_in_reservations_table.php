<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modifier l'ENUM existant pour inclure 'annulee'
        DB::statement("ALTER TABLE reservations MODIFY statut ENUM('en_attente', 'acceptee', 'terminee', 'annulee') DEFAULT 'en_attente'");
    }

    public function down(): void
    {
        // Revenir en arrière si besoin (retirer 'annulee')
        DB::statement("ALTER TABLE reservations MODIFY statut ENUM('en_attente', 'acceptee', 'terminee') DEFAULT 'en_attente'");
    }
};
