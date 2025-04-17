<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sms_gateway_url')->nullable()->after('remember_token'); // Or choose another position
            $table->string('sms_gateway_username')->nullable()->after('sms_gateway_url');
            $table->text('sms_gateway_password_encrypted')->nullable()->after('sms_gateway_username'); // Store encrypted password
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sms_gateway_url',
                'sms_gateway_username',
                'sms_gateway_password_encrypted',
            ]);
        });
    }
};