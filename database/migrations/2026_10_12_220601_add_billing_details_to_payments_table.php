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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('currency');
            $table->string('billing_document', 30)->nullable()->after('billing_name');
            $table->string('billing_country', 2)->nullable()->after('billing_document');
            $table->string('billing_phone', 30)->nullable()->after('billing_country');
            $table->timestamp('terms_accepted_at')->nullable()->after('billing_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_document', 'billing_country', 'billing_phone', 'terms_accepted_at']);
        });
    }
};
