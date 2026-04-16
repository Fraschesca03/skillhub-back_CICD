<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            // Prix en euros — 0 = gratuit
            $table->decimal('prix', 8, 2)->default(0)->after('niveau');
            // Duree en heures
            $table->integer('duree_heures')->default(0)->after('prix');
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn(['prix', 'duree_heures']);
        });
    }
};