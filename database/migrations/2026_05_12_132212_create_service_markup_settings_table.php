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
        Schema::create('service_markup_settings', function (Blueprint $table) {
            $table->id();

            // Service yang diatur markup-nya
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // Tipe markup: 'percentage' atau 'fixed'
            $table->enum('markup_type', ['percentage', 'fixed']);

            // Nilai markup
            $table->decimal('markup_value', 10, 2)->comment('Persentase (0-100) atau nominal (dalam rupiah)');

            // Minimum harga setelah markup (untuk fixed markup, jika base price terlalu rendah)
            $table->decimal('min_final_price', 12, 2)->nullable()->comment('Harga minimum yang harus dibayar patient');

            // Status setting
            $table->boolean('is_active')->default(true);

            // Prioritas (jika ada multiple settings untuk service yang sama)
            $table->integer('priority')->default(0);

            // Keterangan
            $table->text('notes')->nullable();

            // User yang membuat setting (admin)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // User yang mengupdate setting terakhir
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa
            $table->index(['service_id', 'is_active']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_markup_settings');
    }
};
