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
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('profession', ['dokter', 'apotik']);
            $table->string('pharmacy_name')->nullable();
            $table->string('specialization')->nullable();
            $table->string('license_number')->nullable()->unique();
            $table->string('work_location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('years_of_experience')->default(0);
            $table->decimal('consultation_fee', 12, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['profession', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
