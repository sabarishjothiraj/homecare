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
        // Intentionally a no-op. Shrinking back to 20 fails under strict mode
        // once an email destination has been stored, and truncating it would
        // corrupt the audit trail. varchar(255) holds everything 20 did.
    }
};
