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
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'pharmacy_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('pharmacy_id')->nullable()->after('id')->constrained('pharmacies')->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'pharmacy_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('pharmacy_id')->nullable()->after('patient_user_id')->constrained('pharmacies')->nullOnDelete();
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'pharmacy_user_id') && Schema::hasColumn('products', 'pharmacy_id')) {
            DB::statement("
                UPDATE products p
                INNER JOIN pharmacies ph ON ph.owner_user_id = p.pharmacy_user_id
                SET p.pharmacy_id = ph.id
                WHERE p.pharmacy_id IS NULL
            ");
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_user_id') && Schema::hasColumn('orders', 'pharmacy_id')) {
            DB::statement("
                UPDATE orders o
                INNER JOIN pharmacies ph ON ph.owner_user_id = o.pharmacy_user_id
                SET o.pharmacy_id = ph.id
                WHERE o.pharmacy_id IS NULL
            ");
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'pharmacy_id')) {
            if (! $this->hasIndex('products', 'products_pharmacy_id_is_active_index')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->index(['pharmacy_id', 'is_active'], 'products_pharmacy_id_is_active_index');
                });
            }

            if (! $this->hasIndex('products', 'products_pharmacy_id_sku_unique')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->unique(['pharmacy_id', 'sku'], 'products_pharmacy_id_sku_unique');
                });
            }
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_id')) {
            if (! $this->hasIndex('orders', 'orders_pharmacy_id_status_index')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index(['pharmacy_id', 'status'], 'orders_pharmacy_id_status_index');
                });
            }
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'pharmacy_user_id')) {
            $this->dropForeignKeyByColumn('products', 'pharmacy_user_id');
            $this->dropIndexIfExists('products', 'products_pharmacy_user_id_is_active_index');
            $this->dropIndexIfExists('products', 'products_pharmacy_user_id_sku_unique');

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('pharmacy_user_id');
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_user_id')) {
            $this->dropForeignKeyByColumn('orders', 'pharmacy_user_id');
            $this->dropIndexIfExists('orders', 'orders_pharmacy_user_id_status_index');

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('pharmacy_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'pharmacy_user_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('pharmacy_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'pharmacy_user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('pharmacy_user_id')->nullable()->after('patient_user_id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'pharmacy_id') && Schema::hasColumn('products', 'pharmacy_user_id')) {
            DB::statement("
                UPDATE products p
                INNER JOIN pharmacies ph ON ph.id = p.pharmacy_id
                SET p.pharmacy_user_id = ph.owner_user_id
                WHERE p.pharmacy_user_id IS NULL
            ");
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_id') && Schema::hasColumn('orders', 'pharmacy_user_id')) {
            DB::statement("
                UPDATE orders o
                INNER JOIN pharmacies ph ON ph.id = o.pharmacy_id
                SET o.pharmacy_user_id = ph.owner_user_id
                WHERE o.pharmacy_user_id IS NULL
            ");
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'pharmacy_user_id')) {
            if (! $this->hasIndex('products', 'products_pharmacy_user_id_is_active_index')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->index(['pharmacy_user_id', 'is_active'], 'products_pharmacy_user_id_is_active_index');
                });
            }

            if (! $this->hasIndex('products', 'products_pharmacy_user_id_sku_unique')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->unique(['pharmacy_user_id', 'sku'], 'products_pharmacy_user_id_sku_unique');
                });
            }
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_user_id')) {
            if (! $this->hasIndex('orders', 'orders_pharmacy_user_id_status_index')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->index(['pharmacy_user_id', 'status'], 'orders_pharmacy_user_id_status_index');
                });
            }
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'pharmacy_id')) {
            $this->dropForeignKeyByColumn('products', 'pharmacy_id');
            $this->dropIndexIfExists('products', 'products_pharmacy_id_sku_unique');
            $this->dropIndexIfExists('products', 'products_pharmacy_id_is_active_index');

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('pharmacy_id');
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pharmacy_id')) {
            $this->dropForeignKeyByColumn('orders', 'pharmacy_id');
            $this->dropIndexIfExists('orders', 'orders_pharmacy_id_status_index');

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('pharmacy_id');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->hasIndex($table, $indexName)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    private function dropForeignKeyByColumn(string $table, string $column): void
    {
        $database = DB::getDatabaseName();
        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->first();

        if ($constraint?->CONSTRAINT_NAME) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }
};
