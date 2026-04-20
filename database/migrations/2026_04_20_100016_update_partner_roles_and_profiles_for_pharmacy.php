<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("UPDATE users SET role = 'apotik' WHERE role = 'perawat'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('pasien', 'dokter', 'apotik', 'kurir') NOT NULL DEFAULT 'pasien'");
        }

        if (Schema::hasTable('partner_profiles')) {
            if (Schema::hasColumn('partner_profiles', 'profession')) {
                DB::statement("UPDATE partner_profiles SET profession = 'apotik' WHERE profession = 'perawat'");
                DB::statement("ALTER TABLE partner_profiles MODIFY profession ENUM('dokter', 'apotik') NOT NULL");
            }

            if (! Schema::hasColumn('partner_profiles', 'pharmacy_name')) {
                Schema::table('partner_profiles', function (Blueprint $table) {
                    $table->string('pharmacy_name')->nullable()->after('profession');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('partner_profiles') && Schema::hasColumn('partner_profiles', 'pharmacy_name')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                $table->dropColumn('pharmacy_name');
            });
        }

        if (Schema::hasTable('partner_profiles') && Schema::hasColumn('partner_profiles', 'profession')) {
            DB::statement("UPDATE partner_profiles SET profession = 'perawat' WHERE profession = 'apotik'");
            DB::statement("ALTER TABLE partner_profiles MODIFY profession ENUM('dokter', 'perawat') NOT NULL");
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("UPDATE users SET role = 'perawat' WHERE role = 'apotik'");
            DB::statement("ALTER TABLE users MODIFY role ENUM('pasien', 'dokter', 'perawat', 'kurir') NOT NULL DEFAULT 'pasien'");
        }
    }
};
