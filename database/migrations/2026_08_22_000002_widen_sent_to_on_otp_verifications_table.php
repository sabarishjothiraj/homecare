<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sent_to was sized for phone numbers, but the OTP destination can now be
     * an email address (and later a Telegram chat id), which overflows 20.
     */
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->string('sent_to', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->string('sent_to', 20)->nullable()->change();
        });
    }
};
