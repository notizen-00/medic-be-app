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
        if (Schema::hasTable('partner_profiles')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('partner_profiles', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('work_location');
                }

                if (! Schema::hasColumn('partner_profiles', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('partner_profiles')) {
            Schema::table('partner_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('partner_profiles', 'longitude')) {
                    $table->dropColumn('longitude');
                }

                if (Schema::hasColumn('partner_profiles', 'latitude')) {
                    $table->dropColumn('latitude');
                }
            });
        }
    }
};
