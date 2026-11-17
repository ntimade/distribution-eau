<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('phone_country', 2)->nullable()->after('phone');
        });

        // Le pays vient du plus courant des numéros déjà complets du compte, sinon du sien.
        foreach (DB::table('organisations')->get(['id', 'phone']) as $organisation) {
            $phones = DB::table('customers')->where('organisation_id', $organisation->id)->whereNotNull('phone')->pluck('phone');

            $country = $phones->map(fn ($phone) => PhoneNumber::guessCountry($phone))->filter()->countBy()->sortDesc()->keys()->first()
                ?? PhoneNumber::guessCountry((string) $organisation->phone);

            DB::table('organisations')->where('id', $organisation->id)->update(['phone_country' => $country]);
            PhoneNumber::normalizeOrganisation($organisation->id, $country);
        }
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('phone_country');
        });
    }
};
