<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add structured address fields as nullable first, so existing rows can
        // be backfilled before they become required.
        Schema::table('patients', function (Blueprint $table) {
            $table->string('address1')->nullable()->after('phone');
            $table->string('address2')->nullable()->after('address1');
            $table->string('area')->nullable()->after('address2');
            $table->string('city')->nullable()->after('area');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->string('pincode', 20)->nullable()->after('country');
        });

        // address was TEXT and address1 is varchar(255): keep an over-long
        // address whole in the notes rather than losing its tail.
        DB::table('patients')
            ->whereRaw('CHAR_LENGTH(address) > 255')
            ->update(['notes' => DB::raw("CONCAT_WS('\n\n', notes, CONCAT('Address: ', address))")]);

        // Carry the old values across. State, country and pincode were never
        // captured, so they start blank for the office to fill in on next edit.
        DB::table('patients')->update([
            'address1' => DB::raw('LEFT(address, 255)'),
            'city' => DB::raw('location'),
            'state' => '',
            'country' => '',
            'pincode' => '',
        ]);

        // email used to be optional; blank the missing ones so it can be required.
        DB::table('patients')->whereNull('email')->update(['email' => '']);

        Schema::table('patients', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->string('address1')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            $table->string('state')->nullable(false)->change();
            $table->string('country')->nullable(false)->change();
            $table->string('pincode', 20)->nullable(false)->change();

            // Remove caste, subcaste, old location/address fields
            $table->dropForeign(['caste_id']);
            $table->dropColumn(['caste_id', 'subcaste', 'address', 'location']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->text('address')->nullable()->after('phone');
            $table->foreignId('caste_id')->nullable()->after('address')->constrained('castes')->onDelete('set null');
            $table->string('subcaste', 100)->nullable()->after('caste_id');
            $table->string('location')->nullable()->after('subcaste');
        });

        // Fold the structured address back into the free-text columns.
        DB::table('patients')->update([
            'address' => DB::raw("CONCAT_WS(', ', NULLIF(address1, ''), NULLIF(address2, ''), NULLIF(area, ''), NULLIF(city, ''), NULLIF(state, ''), NULLIF(pincode, ''), NULLIF(country, ''))"),
            'location' => DB::raw('city'),
        ]);

        Schema::table('patients', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->text('address')->nullable(false)->change();
            $table->string('location')->nullable(false)->change();

            $table->dropColumn(['address1', 'address2', 'area', 'city', 'state', 'country', 'pincode']);
        });
    }
};
