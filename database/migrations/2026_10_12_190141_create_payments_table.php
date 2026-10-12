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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('reference')->unique();
            $table->string('gateway', 30);
            $table->string('gateway_payment_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status', 20)->index();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_payment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
