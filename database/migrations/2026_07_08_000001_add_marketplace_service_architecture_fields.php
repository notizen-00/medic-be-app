<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'service_category_id')) {
                $table->foreignId('service_category_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('service_categories')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('services', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            if (! Schema::hasColumn('services', 'service_mode')) {
                $table->string('service_mode', 50)->default('visit')->after('service_type');
            }

            if (! Schema::hasColumn('services', 'requires_address')) {
                $table->boolean('requires_address')->default(true)->after('duration_minutes');
            }

            if (! Schema::hasColumn('services', 'requires_schedule')) {
                $table->boolean('requires_schedule')->default(false)->after('requires_address');
            }

            if (! Schema::hasColumn('services', 'requires_matchmaking')) {
                $table->boolean('requires_matchmaking')->default(true)->after('requires_schedule');
            }

            if (! Schema::hasColumn('services', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('requires_matchmaking');
            }
        });

        Schema::table('partner_services', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_services', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->after('partner_user_id');
            }

            if (! Schema::hasColumn('partner_services', 'is_available')) {
                $table->boolean('is_available')->default(true)->after('is_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_services', function (Blueprint $table) {
            if (Schema::hasColumn('partner_services', 'is_available')) {
                $table->dropColumn('is_available');
            }

            if (Schema::hasColumn('partner_services', 'price')) {
                $table->dropColumn('price');
            }
        });

        Schema::table('services', function (Blueprint $table) {
            foreach ([
                'sort_order',
                'requires_matchmaking',
                'requires_schedule',
                'requires_address',
                'service_mode',
                'slug',
            ] as $column) {
                if (Schema::hasColumn('services', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('services', 'service_category_id')) {
                $table->dropConstrainedForeignId('service_category_id');
            }
        });

        Schema::dropIfExists('service_categories');
    }
};
