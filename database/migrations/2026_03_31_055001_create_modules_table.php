<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Création de la table modules.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();

            // Titre du module
            $table->string('titre');

            // Contenu du module
            $table->text('contenu');

            // Ordre d'affichage du module dans la formation
            $table->integer('ordre');

            // Formation liée
            $table->foreignId('formation_id')
                ->constrained('formations')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Suppression de la table modules.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};