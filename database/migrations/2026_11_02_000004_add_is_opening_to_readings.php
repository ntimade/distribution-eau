<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Le solde de départ repris d'un ancien système est une facture sans relevé :
    // il entre ainsi dans les impayés et reçoit des paiements comme les autres.
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->boolean('is_opening')->default(false)->after('period');
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn('is_opening');
        });
    }
};
