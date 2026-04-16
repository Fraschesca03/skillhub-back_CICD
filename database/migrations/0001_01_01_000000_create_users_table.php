<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Création de la table users.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['apprenant', 'formateur']);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Suppression de la table users.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};