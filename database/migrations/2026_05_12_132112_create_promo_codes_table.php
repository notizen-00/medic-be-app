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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Tipe discount
            $table->enum('discount_type', ['percentage', 'fixed']);

            // Nilai discount
            $table->decimal('discount_value', 12, 2);

            // Minimum transaksi untuk menggunakan promo
            $table->decimal('min_purchase', 12, 2)->default(0);

            // Maximum discount yang bisa didapat (untuk percentage)
            $table->decimal('max_discount', 12, 2)->nullable();

            // Batasan penggunaan
            $table->integer('max_uses')->nullable()->comment('Total penggunaan maksimal (null = unlimited)');
            $table->integer('max_uses_per_user')->default(1)->comment('Penggunaan maksimal per user');

            // Status
            $table->boolean('is_active')->default(true);

            // Validitas
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            // Batasan untuk service tertentu (null = berlaku untuk semua service)
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            // User yang membuat (admin)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa
            $table->index('code');
            $table->index('is_active');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
