<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'name']);
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50);
            $table->decimal('initial_index', 12, 3)->default(0);
            $table->date('installed_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'number']);
        });

        // Un relevé par compteur et par mois. Le client et le prix sont figés au
        // moment de la saisie : changer de tarif ou de client ne réécrit pas le passé.
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('period', 7); // AAAA-MM
            $table->date('read_at');
            $table->decimal('previous_index', 12, 3);
            $table->decimal('current_index', 12, 3);
            $table->decimal('consumption', 12, 3);
            $table->decimal('unit_price', 12, 3);
            $table->decimal('amount', 15, 3);
            $table->decimal('paid_amount', 15, 3)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['meter_id', 'period']);
            $table->index(['organisation_id', 'period']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reading_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 3);
            $table->date('paid_at');
            $table->string('method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organisation_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('zones');
    }
};
