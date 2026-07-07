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
        if (Schema::hasTable('products')) {
            if (Schema::hasColumn('products', 'sku') && ! Schema::hasIndex('products', 'products_pharmacy_user_id_sku_unique')) {
                Schema::table('products', function (Blueprint $table) {
                    if (Schema::hasIndex('products', 'products_sku_unique')) {
                        $table->dropUnique('products_sku_unique');
                    }

                    $table->unique(['pharmacy_user_id', 'sku'], 'products_pharmacy_user_id_sku_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasIndex('products', 'products_pharmacy_user_id_sku_unique')) {
                    $table->dropUnique('products_pharmacy_user_id_sku_unique');
                }

                if (! Schema::hasIndex('products', 'products_sku_unique')) {
                    $table->unique('sku');
                }
            });
        }
    }
};
