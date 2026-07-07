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
        if ($this->isSqlite()) {
            return;
        }

        DB::statement("ALTER TABLE products MODIFY type ENUM('obat', 'produk_kesehatan', 'layanan', 'sewa_alat_kesehatan') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        DB::statement("ALTER TABLE products MODIFY type ENUM('obat', 'produk_kesehatan') NOT NULL");
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
