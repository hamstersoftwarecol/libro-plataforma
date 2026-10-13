<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Books are sold individually with lifetime access instead of subscription plans.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->nullable()->after('page_count');
            $table->string('currency', 3)->default('COP')->after('price');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->change();
            $table->foreignId('book_id')->nullable()->after('plan_id')->constrained()->nullOnDelete();
        });

        Schema::create('book_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamps();
            $table->unique(['user_id', 'book_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_user');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_id');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['price', 'currency']);
        });
    }
};
