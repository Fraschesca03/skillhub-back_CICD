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
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('utilisateur_id');
            $table->unsignedBigInteger('formation_id');
            $table->integer('progression')->default(0);

            $table->timestamps();

            $table->foreign('utilisateur_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('formation_id')
                ->references('id')
                ->on('formations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};