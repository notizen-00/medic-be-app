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
        Schema::table('service_bookings', function (Blueprint $table) {
            // Promo/Discount code yang digunakan
            $table->string('promo_code')->nullable()->after('notes');

            // Discount yang diterapkan (dalam rupiah)
            $table->decimal('discount_amount', 12, 2)->default(0)->after('promo_code');

            // Discount type: 'percentage' atau 'fixed'
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->after('discount_amount');

            // Total setelah discount
            $table->decimal('subtotal', 12, 2)->default(0)->after('total_amount');

            // Markup amount dari admin (dalam rupiah)
            $table->decimal('markup_amount', 12, 2)->default(0)->after('subtotal');

            // Update total_amount menjadi final price setelah discount dan markup
            // total_amount = (base_price + markup) - discount
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropColumn(['promo_code', 'discount_amount', 'discount_type', 'subtotal', 'markup_amount']);
        });
    }
};
