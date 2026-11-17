<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->timestamp('whatsapp_opened_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_opened_at');
        });
    }
};
