<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('admin')->after('organisation_id');
            // Choix explicite : un releveur sans zone ne voit rien, il ne reçoit jamais « toutes les zones » par accident.
            $table->boolean('all_zones')->default(false)->after('role');
        });

        Schema::create('reader_zone', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_zone');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'all_zones']);
        });
    }
};
