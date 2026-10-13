<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments can start before an account exists: the account is created once the payment is confirmed.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable()->after('user_id')->index();
            $table->boolean('created_account')->default(false)->after('terms_accepted_at');
        });

        DB::table('payments')->whereNull('email')->update([
            'email' => DB::raw('(select email from users where users.id = payments.user_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['email', 'created_account']);
        });
    }
};
