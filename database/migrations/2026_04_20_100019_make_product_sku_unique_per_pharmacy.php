<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            $indexNames = collect(DB::select("SHOW INDEX FROM products"))->pluck('Key_name');

            if (Schema::hasColumn('products', 'sku') && ! $indexNames->contains('products_pharmacy_user_id_sku_unique')) {
                Schema::table('products', function (Blueprint $table) use ($indexNames) {
                    if ($indexNames->contains('products_sku_unique')) {
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
            $indexes = collect(DB::select("SHOW INDEX FROM products"))->pluck('Key_name');

            Schema::table('products', function (Blueprint $table) use ($indexes) {
                if ($indexes->contains('products_pharmacy_user_id_sku_unique')) {
                    $table->dropUnique('products_pharmacy_user_id_sku_unique');
                }

                if (! $indexes->contains('products_sku_unique')) {
                    $table->unique('sku');
                }
            });
        }
    }
};
