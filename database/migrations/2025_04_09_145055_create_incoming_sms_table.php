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
        Schema::create('incoming_sms', function (Blueprint $table) {
            $table->id();
            $table->string('sender');
            $table->text('message');
            $table->timestamp('received_at')->nullable();
            $table->unsignedTinyInteger('sim_number')->nullable();
            $table->string('gateway_message_id')->nullable()->index(); // ID from webhook payload
            $table->json('raw_payload')->nullable(); // Store full payload for debugging/auditing
            $table->timestamps(); // Laravel's created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_sms');
    }
};
