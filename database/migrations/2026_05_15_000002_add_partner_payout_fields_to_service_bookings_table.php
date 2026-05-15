<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('scheduled_at');
            $table->timestamp('partner_paid_at')->nullable()->after('completed_at');
            $table->foreignId('partner_balance_transaction_id')
                ->nullable()
                ->after('partner_paid_at')
                ->constrained('balance_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_balance_transaction_id');
            $table->dropColumn(['accepted_at', 'partner_paid_at']);
        });
    }
};
