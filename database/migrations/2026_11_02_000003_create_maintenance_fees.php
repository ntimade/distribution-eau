<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Au plus un montant par mois : les frais ne peuvent pas s'appliquer deux fois.
        Schema::create('maintenance_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // AAAA-MM
            $table->decimal('amount', 15, 3);
            $table->timestamps();
            $table->unique(['organisation_id', 'period']);
        });

        Schema::table('readings', function (Blueprint $table) {
            $table->decimal('maintenance_fee', 15, 3)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn('maintenance_fee');
        });

        Schema::dropIfExists('maintenance_fees');
    }
};
