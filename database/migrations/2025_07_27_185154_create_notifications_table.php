<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable'); // crée notifiable_id et notifiable_type
            $table->text('data'); // JSON contenant les infos
            $table->unsignedBigInteger('reservation_id')->nullable()->index(); // Ajout de la colonne reservation_id
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Si tu veux, tu peux ajouter une contrainte étrangère :
            // $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
