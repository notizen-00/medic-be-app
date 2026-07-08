<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_booking_partner_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_booking_id')->unique()->constrained('service_bookings')->cascadeOnDelete();
            $table->foreignId('partner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('speed_mps', 8, 2)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['partner_user_id', 'recorded_at'], 'sb_partner_loc_partner_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_booking_partner_locations');
    }
};
