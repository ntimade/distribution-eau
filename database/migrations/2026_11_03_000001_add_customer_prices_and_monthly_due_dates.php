<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vide : le client paie le tarif général de l'organisation.
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('price_per_m3', 12, 3)->nullable()->after('address');
        });

        // Date d'échéance commune à toutes les factures d'un mois.
        Schema::create('monthly_due_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // AAAA-MM
            $table->date('due_date');
            $table->timestamps();
            $table->unique(['organisation_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_due_dates');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('price_per_m3');
        });
    }
};
