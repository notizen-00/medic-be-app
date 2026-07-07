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
        if (! Schema::hasTable('pharmacy_profiles')) {
            Schema::create('pharmacy_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
                $table->string('name');
                $table->string('license_number')->nullable()->unique();
                $table->string('address', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->time('opening_time')->nullable();
                $table->time('closing_time')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique('pharmacy_id');
                $table->index(['name', 'opening_time', 'closing_time'], 'pharmacy_profiles_name_hours_index');
            });
        }

        if (
            Schema::hasTable('pharmacies')
            && Schema::hasTable('pharmacy_profiles')
            && Schema::hasColumn('pharmacies', 'name')
            && ! $this->isSqlite()
        ) {
            DB::statement("
                INSERT INTO pharmacy_profiles (
                    pharmacy_id, name, license_number, address, latitude, longitude, description, created_at, updated_at
                )
                SELECT
                    ph.id,
                    COALESCE(NULLIF(ph.name, ''), CONCAT('Apotik #', ph.id)),
                    ph.license_number,
                    ph.address,
                    ph.latitude,
                    ph.longitude,
                    ph.description,
                    COALESCE(ph.created_at, NOW()),
                    COALESCE(ph.updated_at, NOW())
                FROM pharmacies ph
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM pharmacy_profiles pp
                    WHERE pp.pharmacy_id = ph.id
                )
            ");
        }

        if (! $this->isSqlite() && Schema::hasTable('pharmacies')) {
            Schema::table('pharmacies', function (Blueprint $table) {
                foreach (['name', 'license_number', 'address', 'latitude', 'longitude', 'description'] as $column) {
                    if (Schema::hasColumn('pharmacies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pharmacies') && ! Schema::hasColumn('pharmacies', 'name')) {
            Schema::table('pharmacies', function (Blueprint $table) {
                $table->string('name')->nullable();
                $table->string('license_number')->nullable()->unique();
                $table->string('address', 500)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('description')->nullable();
            });
        }

        if (
            Schema::hasTable('pharmacies')
            && Schema::hasTable('pharmacy_profiles')
            && Schema::hasColumn('pharmacies', 'name')
            && ! $this->isSqlite()
        ) {
            DB::statement("
                UPDATE pharmacies ph
                INNER JOIN pharmacy_profiles pp ON pp.pharmacy_id = ph.id
                SET
                    ph.name = pp.name,
                    ph.license_number = pp.license_number,
                    ph.address = pp.address,
                    ph.latitude = pp.latitude,
                    ph.longitude = pp.longitude,
                    ph.description = pp.description
            ");
        }

        Schema::dropIfExists('pharmacy_profiles');
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
