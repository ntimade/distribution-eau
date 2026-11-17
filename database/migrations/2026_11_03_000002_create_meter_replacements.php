<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_meter_id')->unique()->constrained('meters')->restrictOnDelete();
            $table->foreignId('new_meter_id')->unique()->constrained('meters')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('replaced_on');
            $table->decimal('old_previous_index', 12, 3);
            $table->decimal('old_final_index', 12, 3);
            $table->decimal('old_consumption', 12, 3);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // La consommation de l'ancien compteur est facturée avec le premier relevé du nouveau.
        Schema::table('readings', function (Blueprint $table) {
            $table->foreignId('meter_replacement_id')->nullable()->after('meter_id')->constrained()->restrictOnDelete();
            $table->decimal('carried_consumption', 12, 3)->default(0)->after('consumption');
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meter_replacement_id');
            $table->dropColumn('carried_consumption');
        });

        Schema::dropIfExists('meter_replacements');
    }
};
