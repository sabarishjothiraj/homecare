<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Make email required
            $table->string('email')->nullable(false)->change();

            // Remove caste, subcaste, old location/address fields
            $table->dropForeign(['caste_id']);
            $table->dropColumn(['caste_id', 'subcaste', 'address', 'location']);

            // Add structured address fields
            $table->string('address1')->after('phone');
            $table->string('address2')->nullable()->after('address1');
            $table->string('area')->nullable()->after('address2');
            $table->string('city')->after('area');
            $table->string('state')->after('city');
            $table->string('country')->after('state');
            $table->string('pincode', 20)->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('email')->nullable()->change();

            $table->dropColumn(['address1', 'address2', 'area', 'city', 'state', 'country', 'pincode']);

            $table->text('address')->after('phone');
            $table->foreignId('caste_id')->nullable()->after('address')->constrained('castes')->onDelete('set null');
            $table->string('subcaste', 100)->nullable()->after('caste_id');
            $table->string('location')->after('subcaste');
        });
    }
};
