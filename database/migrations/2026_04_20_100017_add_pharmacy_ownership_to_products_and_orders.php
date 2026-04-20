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
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'pharmacy_user_id')) {
                    $table->foreignId('pharmacy_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                    $table->index(['pharmacy_user_id', 'is_active']);
                }

                if (! Schema::hasColumn('products', 'minimum_stock_alert')) {
                    $table->unsignedInteger('minimum_stock_alert')->default(5)->after('stock');
                }

                if (! Schema::hasColumn('products', 'track_stock')) {
                    $table->boolean('track_stock')->default(true)->after('minimum_stock_alert');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'pharmacy_user_id')) {
                    $table->foreignId('pharmacy_user_id')->nullable()->after('patient_user_id')->constrained('users')->nullOnDelete();
                    $table->index(['pharmacy_user_id', 'status']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['pharmacy_user_id', 'status']);
                $table->dropConstrainedForeignId('pharmacy_user_id');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'pharmacy_user_id')) {
                    $table->dropIndex(['pharmacy_user_id', 'is_active']);
                    $table->dropConstrainedForeignId('pharmacy_user_id');
                }

                if (Schema::hasColumn('products', 'track_stock')) {
                    $table->dropColumn('track_stock');
                }

                if (Schema::hasColumn('products', 'minimum_stock_alert')) {
                    $table->dropColumn('minimum_stock_alert');
                }
            });
        }
    }
};
