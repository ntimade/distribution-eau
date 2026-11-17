<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutoff_notices', function (Blueprint $table) {
            $table->timestamp('message_opened_at')->nullable()->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('cutoff_notices', function (Blueprint $table) {
            $table->dropColumn('message_opened_at');
        });
    }
};
