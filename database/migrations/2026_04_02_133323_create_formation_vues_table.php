<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Création de la table formation_vues.
 * Trace les vues uniques par utilisateur par formation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_vues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->onDelete('cascade');
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('ip')->nullable();
            $table->timestamps();

            // Une seule vue par utilisateur connecté par formation
            $table->unique(['formation_id', 'utilisateur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_vues');
    }
};