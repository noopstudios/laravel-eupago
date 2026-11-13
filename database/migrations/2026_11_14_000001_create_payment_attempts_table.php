<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->string('identifier')->unique();
            $table->string('method');
            $table->unsignedInteger('amount_cents');
            $table->string('entity')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('trid')->nullable();
            $table->string('status')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
