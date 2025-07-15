<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transporteurs', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->longText('adresse')->nullable();
            $table->longText('telephone')->nullable();
            $table->longText('photo_profil')->nullable();
            $table->enum('status', ['disponible', 'indisponible'])->default('disponible');
        });
    }

    public function down(): void
    {
        Schema::table('transporteurs', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'adresse', 'telephone', 'status']);
        });
    }
};
