<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_booking_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['status', 'treatment', 'note'])->default('note');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['service_booking_id', 'type']);
            $table->index(['actor_user_id', 'handled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_booking_histories');
    }
};
