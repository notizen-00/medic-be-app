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
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique()->index();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('balance_id')->constrained('user_balances')->onDelete('cascade');

            // Jenis transaksi: topup, refund, deduction, adjustment, transfer, payment
            $table->enum('type', [
                'topup',        // Penambahan saldo (top up)
                'refund',       // Refund dari transaksi
                'deduction',    // Pengurangan saldo (pembayaran)
                'adjustment',   // Adjust manual oleh admin
                'transfer',     // Transfer antar user
                'payment'       // Pembayaran layanan
            ]);

            // Status transaksi
            $table->enum('status', [
                'pending',      // Transaksi masih diproses
                'completed',    // Transaksi berhasil
                'failed',       // Transaksi gagal
                'cancelled'     // Transaksi dibatalkan
            ])->default('pending');

            // Jumlah transaksi
            $table->decimal('amount', 15, 2)->comment('Jumlah transaksi (positif untuk penambahan, negatif untuk pengurangan)');
            $table->decimal('balance_before', 15, 2)->comment('Saldo sebelum transaksi');
            $table->decimal('balance_after', 15, 2)->comment('Saldo setelah transaksi');

            // Reference ke transaksi eksternal (order_id, payment_id, dll)
            $table->string('reference_type')->nullable()->comment('Tipe referensi: order, payment, consultation, dll');
            $table->unsignedBigInteger('reference_id')->nullable()->index();

            // Metadata tambahan (JSON)
            $table->json('meta')->nullable()->comment('Metadata tambahan seperti info gateway, invoice_number, dll');

            // Keterangan transaksi
            $table->string('description')->nullable();
            $table->string('reference_number')->unique()->nullable()->comment('Nomor referensi unik untuk transaksi');

            // User yang melakukan adjustment (untuk admin)
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa
            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');
    }
};
