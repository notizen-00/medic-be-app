<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->decimal('partner_current_latitude', 10, 7)->nullable()->after('partner_balance_transaction_id');
            $table->decimal('partner_current_longitude', 10, 7)->nullable()->after('partner_current_latitude');
            $table->decimal('partner_location_accuracy_meters', 8, 2)->nullable()->after('partner_current_longitude');
            $table->decimal('partner_location_heading', 6, 2)->nullable()->after('partner_location_accuracy_meters');
            $table->decimal('partner_location_speed_mps', 8, 2)->nullable()->after('partner_location_heading');
            $table->timestamp('partner_location_updated_at')->nullable()->after('partner_location_speed_mps');
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'partner_current_latitude',
                'partner_current_longitude',
                'partner_location_accuracy_meters',
                'partner_location_heading',
                'partner_location_speed_mps',
                'partner_location_updated_at',
            ]);
        });
    }
};
