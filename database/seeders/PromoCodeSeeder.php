<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dapatkan admin user untuk created_by
        $adminUser = User::where('role', 'admin')->first();

        // Promo code 1: Diskon 10% untuk semua service
        PromoCode::create([
            'code' => 'DISKON10',
            'name' => 'Diskon 10% Semua Service',
            'description' => 'Dapatkan diskon 10% untuk semua layanan dengan minimum pembelian Rp 50.000',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_purchase' => 50000,
            'max_discount' => 50000, // Max discount Rp 50.000
            'max_uses' => 100,
            'max_uses_per_user' => 3,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(6),
            'service_id' => null, // Berlaku untuk semua service
            'created_by' => $adminUser?->id,
        ]);

        // Promo code 2: Diskon flat Rp 20.000
        PromoCode::create([
            'code' => 'HEMAT20',
            'name' => 'Hemat Rp 20.000',
            'description' => 'Dapatkan potongan langsung Rp 20.000 dengan minimum pembelian Rp 100.000',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'min_purchase' => 100000,
            'max_uses' => 50,
            'max_uses_per_user' => 1,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
            'service_id' => null,
            'created_by' => $adminUser?->id,
        ]);

        // Promo code 3: Diskon 15% untuk semua service
        PromoCode::create([
            'code' => 'NEWUSER15',
            'name' => 'Diskon Pengguna Baru 15%',
            'description' => 'Promo khusus pengguna baru, diskon 15% maksimal Rp 30.000',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'min_purchase' => 0,
            'max_discount' => 30000,
            'max_uses' => null, // Unlimited
            'max_uses_per_user' => 1,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'service_id' => null,
            'created_by' => $adminUser?->id,
        ]);

        // Promo code 4: Diskon 20% khusus service tertentu (akan diupdate jika ada service)
        PromoCode::create([
            'code' => 'SPESIAL20',
            'name' => 'Diskon Spesial 20%',
            'description' => 'Diskon 20% untuk layanan tertentu dengan minimum pembelian Rp 75.000',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'min_purchase' => 75000,
            'max_discount' => 100000,
            'max_uses' => 30,
            'max_uses_per_user' => 2,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(2),
            'service_id' => null,
            'created_by' => $adminUser?->id,
        ]);

        // Promo code 5: Diskon flat Rp 50.000 untuk transaksi besar
        PromoCode::create([
            'code' => 'BIGSAVE50',
            'name' => 'Hemat Rp 50.000',
            'description' => 'Potongan Rp 50.000 untuk transaksi minimal Rp 300.000',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'min_purchase' => 300000,
            'max_uses' => 20,
            'max_uses_per_user' => 1,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonth(),
            'service_id' => null,
            'created_by' => $adminUser?->id,
        ]);

        // Promo code 6: Promo inactive untuk testing
        PromoCode::create([
            'code' => 'EXPIRED2024',
            'name' => 'Promo Kadaluarsa',
            'description' => 'Promo yang sudah tidak aktif untuk testing',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'min_purchase' => 0,
            'max_uses' => null,
            'max_uses_per_user' => 1,
            'is_active' => false,
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->subDay(),
            'service_id' => null,
            'created_by' => $adminUser?->id,
        ]);

        $this->command->info('Promo codes berhasil di-seed!');
    }
}
