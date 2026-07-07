<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE services
            MODIFY service_type ENUM(
                'consultation',
                'procedure',
                'caregiver',
                'homecare',
                'dokter_homecare',
                'perawat_homecare',
                'bidan_homecare',
                'konsultasi_tindakan'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("
            ALTER TABLE services
            MODIFY service_type ENUM(
                'dokter_homecare',
                'perawat_homecare',
                'bidan_homecare',
                'konsultasi_tindakan'
            ) NOT NULL
        ");
    }
};
