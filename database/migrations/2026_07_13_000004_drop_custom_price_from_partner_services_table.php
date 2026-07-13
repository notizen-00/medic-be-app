<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_services') || ! Schema::hasColumn('partner_services', 'custom_price')) {
            return;
        }

        if (Schema::hasColumn('partner_services', 'price')) {
            DB::table('partner_services')
                ->whereNull('price')
                ->whereNotNull('custom_price')
                ->update(['price' => DB::raw('custom_price')]);
        }

        Schema::table('partner_services', function (Blueprint $table) {
            $table->dropColumn('custom_price');
        });

        if (Schema::hasColumn('partner_services', 'price') && Schema::hasTable('services')) {
            DB::table('partner_services')
                ->join('services', 'services.id', '=', 'partner_services.service_id')
                ->where('services.service_type', '!=', 'consultation')
                ->whereNotIn('services.service_mode', ['chat', 'voice', 'video'])
                ->select('partner_services.id', 'services.base_price')
                ->orderBy('partner_services.id')
                ->chunk(100, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('partner_services')
                            ->where('id', $row->id)
                            ->update(['price' => $row->base_price]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_services') || Schema::hasColumn('partner_services', 'custom_price')) {
            return;
        }

        Schema::table('partner_services', function (Blueprint $table) {
            $table->decimal('custom_price', 12, 2)->nullable()->after('price');
        });

        if (Schema::hasColumn('partner_services', 'price')) {
            DB::table('partner_services')->update(['custom_price' => DB::raw('price')]);
        }
    }
};
