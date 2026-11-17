<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_DUE_DAYS = 15;

    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_due_days')->default(self::DEFAULT_DUE_DAYS)->after('price_per_m3');
            $table->unsignedSmallInteger('cutoff_grace_days')->default(7)->after('payment_due_days');
        });

        Schema::table('readings', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('read_at');
            $table->index(['organisation_id', 'due_date']);
        });

        // Les factures déjà saisies reçoivent le délai par défaut.
        DB::table('readings')->orderBy('id')->select('id', 'read_at')->each(function ($reading) {
            DB::table('readings')->where('id', $reading->id)->update([
                'due_date' => Carbon::parse($reading->read_at)->addDays(self::DEFAULT_DUE_DAYS)->toDateString(),
            ]);
        });

        // La date limite reste celle de l'avis, même s'il est réimprimé plus tard.
        Schema::create('cutoff_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issued_at');
            $table->date('deadline');
            $table->timestamps();
            $table->index(['organisation_id', 'meter_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutoff_notices');

        Schema::table('readings', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'due_date']);
            $table->dropColumn('due_date');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['payment_due_days', 'cutoff_grace_days']);
        });
    }
};
