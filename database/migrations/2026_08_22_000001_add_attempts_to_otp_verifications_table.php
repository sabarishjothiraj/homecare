<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track how many codes have been tried against a single OTP session so a
     * six-digit code cannot simply be enumerated.
     */
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('otp_code');
            $table->string('sent_to', 20)->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'sent_to']);
        });
    }
};
