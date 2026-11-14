<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->string('event_key')->unique();
            $table->string('identifier')->nullable()->index();
            $table->text('payload');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
