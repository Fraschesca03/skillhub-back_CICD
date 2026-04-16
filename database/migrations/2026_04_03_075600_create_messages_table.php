<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : table des messages entre utilisateurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // Expéditeur du message
            $table->foreignId('expediteur_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Destinataire du message
            $table->foreignId('destinataire_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Contenu du message
            $table->text('contenu');

            // Indique si le destinataire a lu le message
            $table->boolean('lu')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};