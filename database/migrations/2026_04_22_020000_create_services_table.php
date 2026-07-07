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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_code')->unique();
            $table->string('name');
            $table->enum('service_type', [
                'consultation',
                'procedure',
                'caregiver',
                'homecare',
                'dokter_homecare',
                'perawat_homecare',
                'bidan_homecare',
                'konsultasi_tindakan',
            ]);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_homecare')->default(true);
            $table->timestamps();

            $table->index(['service_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
