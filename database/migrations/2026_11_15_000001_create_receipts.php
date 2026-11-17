<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encaissement de plusieurs mois en une fois : le client choisit librement les mois à régler, sur un ou
 * plusieurs de ses compteurs. Chaque mois reçoit son propre paiement, relié à l'encaissement, qui sert de reçu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total', 15, 3);
            $table->date('paid_at');
            $table->string('method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->timestamp('whatsapp_opened_at')->nullable();
            $table->timestamp('sms_opened_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organisation_id', 'paid_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->after('reading_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_id');
        });
        Schema::dropIfExists('receipts');
    }
};
