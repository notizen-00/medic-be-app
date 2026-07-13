<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_booking_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('transport_distance_threshold_km', 8, 2)->default(10);
            $table->decimal('transport_fee_per_visit', 12, 2)->default(0);
            $table->decimal('hospital_meal_fee_per_visit', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->string('visit_plan', 20)->default('once')->after('booking_type');
            $table->string('recurrence', 20)->nullable()->after('visit_plan');
            $table->unsignedSmallInteger('visit_count')->default(1)->after('recurrence');
            $table->string('care_mode', 20)->default('visit')->after('visit_count');
            $table->string('location_type', 20)->default('home')->after('care_mode');
            $table->decimal('distance_km', 8, 2)->nullable()->after('location_type');
            $table->decimal('transport_fee', 12, 2)->default(0)->after('markup_amount');
            $table->decimal('meal_fee', 12, 2)->default(0)->after('transport_fee');
            $table->json('fee_policy_snapshot')->nullable()->after('meal_fee');
            $table->index(['visit_plan', 'recurrence']);
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropIndex(['visit_plan', 'recurrence']);
            $table->dropColumn(['visit_plan', 'recurrence', 'visit_count', 'care_mode', 'location_type', 'distance_km', 'transport_fee', 'meal_fee', 'fee_policy_snapshot']);
        });

        Schema::dropIfExists('service_booking_fee_settings');
    }
};
