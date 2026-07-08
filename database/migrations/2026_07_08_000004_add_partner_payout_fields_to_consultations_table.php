<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->timestamp('partner_paid_at')->nullable()->after('ended_at');
            $table->foreignId('partner_balance_transaction_id')
                ->nullable()
                ->after('partner_paid_at')
                ->constrained('balance_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_balance_transaction_id');
            $table->dropColumn('partner_paid_at');
        });
    }
};
