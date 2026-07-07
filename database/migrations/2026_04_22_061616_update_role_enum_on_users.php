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
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users 
            MODIFY role ENUM('pasien','dokter','apotik','kurir','admin','mitra') 
            DEFAULT 'pasien'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
