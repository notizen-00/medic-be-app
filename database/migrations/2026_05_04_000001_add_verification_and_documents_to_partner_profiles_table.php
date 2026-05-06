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
        Schema::table('partner_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_profiles', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->after('bio');
            }

            if (! Schema::hasColumn('partner_profiles', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_status');
            }

            if (! Schema::hasColumn('partner_profiles', 'verified_by_user_id')) {
                $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('verified_at');
            }

            if (! Schema::hasColumn('partner_profiles', 'str_photo_path')) {
                $table->string('str_photo_path')->nullable()->after('verified_by_user_id');
            }

            if (! Schema::hasColumn('partner_profiles', 'ktp_photo_path')) {
                $table->string('ktp_photo_path')->nullable()->after('str_photo_path');
            }
        });

        if (Schema::hasColumn('partner_profiles', 'verification_status')) {
            DB::table('partner_profiles')
                ->where('verification_status', 'pending')
                ->update([
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('partner_profiles', 'ktp_photo_path')) {
                $table->dropColumn('ktp_photo_path');
            }

            if (Schema::hasColumn('partner_profiles', 'str_photo_path')) {
                $table->dropColumn('str_photo_path');
            }

            if (Schema::hasColumn('partner_profiles', 'verified_by_user_id')) {
                $table->dropConstrainedForeignId('verified_by_user_id');
            }

            if (Schema::hasColumn('partner_profiles', 'verified_at')) {
                $table->dropColumn('verified_at');
            }

            if (Schema::hasColumn('partner_profiles', 'verification_status')) {
                $table->dropColumn('verification_status');
            }
        });
    }
};
