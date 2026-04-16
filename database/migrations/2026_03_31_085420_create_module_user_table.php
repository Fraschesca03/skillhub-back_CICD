<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_user', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('utilisateur_id');
            $table->unsignedBigInteger('module_id');
            $table->boolean('termine')->default(true);

            $table->timestamps();

            $table->foreign('utilisateur_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('module_id')
                ->references('id')
                ->on('modules')
                ->onDelete('cascade');

            $table->unique(['utilisateur_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_user');
    }
};