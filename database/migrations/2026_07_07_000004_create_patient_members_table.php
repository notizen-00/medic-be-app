<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->enum('gender', ['laki-laki', 'perempuan'])->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_notes')->nullable();
            $table->string('address_label')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['owner_user_id', 'is_primary']);
            $table->index(['owner_user_id', 'relationship']);
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->foreignId('patient_member_id')
                ->nullable()
                ->after('patient_user_id')
                ->constrained('patient_members')
                ->nullOnDelete();

            $table->index(['patient_member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropIndex(['patient_member_id', 'status']);
            $table->dropConstrainedForeignId('patient_member_id');
        });

        Schema::dropIfExists('patient_members');
    }
};
