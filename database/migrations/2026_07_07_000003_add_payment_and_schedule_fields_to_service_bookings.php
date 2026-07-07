<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->enum('booking_type', ['scheduled', 'daily'])->default('scheduled')->after('status');
            $table->timestamp('schedule_start_at')->nullable()->after('scheduled_at');
            $table->timestamp('schedule_end_at')->nullable()->after('schedule_start_at');
            $table->unsignedSmallInteger('duration_days')->default(1)->after('schedule_end_at');

            $table->index(['booking_type', 'scheduled_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('service_booking_id')
                ->nullable()
                ->after('consultation_id')
                ->constrained('service_bookings')
                ->nullOnDelete();

            $table->index(['service_booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['service_booking_id', 'status']);
            $table->dropConstrainedForeignId('service_booking_id');
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropIndex(['booking_type', 'scheduled_at']);
            $table->dropColumn(['booking_type', 'schedule_start_at', 'schedule_end_at', 'duration_days']);
        });
    }
};
