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
        Schema::create('transactions', function (Blueprint $table) {
            $table->ULID('id')->primary();
            $table->foreignUlId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignUlId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('deposit');
            $table->string('status')->default('pending');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('order_id')->unique();
            $table->string('payment_gateway_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('bank_id')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('processed_at')->nullable(); // this is for our side successfully received the payment and already added / deducted the amount from the wallet, so if something failed, we can prevent double credit / debit the wallet
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
