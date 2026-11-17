<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Le prix du m³ se règle dans les paramètres du compte, après l'inscription.
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->decimal('price_per_m3', 12, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->decimal('price_per_m3', 12, 3)->nullable(false)->change();
        });
    }
};
