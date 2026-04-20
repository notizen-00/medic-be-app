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
        Schema::create('shipment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['waiting_courier', 'picked_up', 'on_delivery', 'delivered', 'failed', 'cancelled']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'logged_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_histories');
    }
};
