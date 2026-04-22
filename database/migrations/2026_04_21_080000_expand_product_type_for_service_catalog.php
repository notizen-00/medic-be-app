<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE products MODIFY type ENUM('obat', 'produk_kesehatan', 'layanan', 'sewa_alat_kesehatan') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY type ENUM('obat', 'produk_kesehatan') NOT NULL");
    }
};
