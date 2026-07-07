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
        if (! Schema::hasTable('pharmacies')) {
            Schema::create('pharmacies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('license_number')->nullable()->unique();
                $table->string('address', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_active')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique('owner_user_id');
                $table->index(['is_active', 'name']);
            });
        }

        if (
            Schema::hasTable('users')
            && Schema::hasTable('partner_profiles')
            && Schema::hasTable('pharmacies')
            && Schema::hasColumn('users', 'role')
            && Schema::hasColumn('partner_profiles', 'profession')
            && ! $this->isSqlite()
        ) {
            $pharmacyNameExpression = Schema::hasColumn('partner_profiles', 'pharmacy_name')
                ? "COALESCE(NULLIF(pp.pharmacy_name, ''), NULLIF(u.name, ''), CONCAT('Apotik #', u.id))"
                : "COALESCE(NULLIF(u.name, ''), CONCAT('Apotik #', u.id))";

            DB::statement("
                INSERT INTO pharmacies (
                    owner_user_id, name, license_number, address, latitude, longitude, is_active, description, created_at, updated_at
                )
                SELECT
                    u.id,
                    {$pharmacyNameExpression},
                    pp.license_number,
                    pp.work_location,
                    pp.latitude,
                    pp.longitude,
                    COALESCE(pp.is_available, 0),
                    pp.bio,
                    NOW(),
                    NOW()
                FROM users u
                LEFT JOIN partner_profiles pp ON pp.user_id = u.id
                WHERE (
                    u.role = 'apotik'
                    OR pp.profession = 'apotik'
                    OR EXISTS (SELECT 1 FROM products p WHERE p.pharmacy_user_id = u.id)
                    OR EXISTS (SELECT 1 FROM orders o WHERE o.pharmacy_user_id = u.id)
                )
                AND NOT EXISTS (
                    SELECT 1 FROM pharmacies ph WHERE ph.owner_user_id = u.id
                )
            ");
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("UPDATE users SET role = 'mitra' WHERE role IN ('dokter', 'apotik', 'kurir', 'perawat', 'bidan')");

            if (! $this->isSqlite()) {
                DB::statement("ALTER TABLE users MODIFY role ENUM('pasien', 'mitra') NOT NULL DEFAULT 'pasien'");
            }
        }

        if (Schema::hasTable('partner_profiles') && Schema::hasColumn('partner_profiles', 'profession')) {
            DB::statement("UPDATE partner_profiles SET profession = 'perawat' WHERE profession = 'apotik'");

            if (! $this->isSqlite()) {
                DB::statement("ALTER TABLE partner_profiles MODIFY profession ENUM('dokter', 'bidan', 'perawat') NOT NULL");
            }
        }

        if (Schema::hasTable('partner_profiles') && Schema::hasColumn('partner_profiles', 'pharmacy_name')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                $table->dropColumn('pharmacy_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('partner_profiles') && ! Schema::hasColumn('partner_profiles', 'pharmacy_name')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                $table->string('pharmacy_name')->nullable()->after('profession');
            });
        }

        if (Schema::hasTable('partner_profiles') && Schema::hasColumn('partner_profiles', 'profession')) {
            DB::statement("UPDATE partner_profiles SET profession = 'apotik' WHERE profession IN ('bidan', 'perawat')");

            if (! $this->isSqlite()) {
                DB::statement("ALTER TABLE partner_profiles MODIFY profession ENUM('dokter', 'apotik') NOT NULL");
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            DB::statement("UPDATE users SET role = 'dokter' WHERE role = 'mitra'");

            if (! $this->isSqlite()) {
                DB::statement("ALTER TABLE users MODIFY role ENUM('pasien', 'dokter', 'apotik', 'kurir') NOT NULL DEFAULT 'pasien'");
            }
        }

        Schema::dropIfExists('pharmacies');
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
