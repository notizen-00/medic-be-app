<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceMarkupSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceMarkupSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dapatkan admin user untuk created_by
        $adminUser = User::where('role', 'admin')->first();

        // Cek apakah ada service di database
        $services = Service::all();

        if ($services->isEmpty()) {
            $this->command->info('Tidak ada service di database. Skipped ServiceMarkupSetting seeding.');
            return;
        }

        // Buat markup settings untuk setiap service yang ada
        foreach ($services as $index => $service) {
            // Hanya buat 1 markup setting per service yang aktif
            ServiceMarkupSetting::create([
                'service_id' => $service->id,
                'markup_type' => $index % 2 === 0 ? 'percentage' : 'fixed', // Alternatif percentage dan fixed
                'markup_value' => $index % 2 === 0 ? 10 + ($index * 5) : 10000 + ($index * 5000), // Percentage 10-30% atau fixed Rp 10.000-30.000
                'min_final_price' => null,
                'is_active' => true,
                'priority' => 1,
                'notes' => "Markup setting default untuk service {$service->name}",
                'created_by' => $adminUser?->id,
                'updated_by' => $adminUser?->id,
            ]);
        }

        // Buat beberapa markup setting khusus untuk testing
        // Markup 25% untuk service pertama (jika ada)
        if ($services->count() >= 1) {
            ServiceMarkupSetting::create([
                'service_id' => $services[0]->id,
                'markup_type' => 'percentage',
                'markup_value' => 25,
                'min_final_price' => 50000,
                'is_active' => false, // Non-aktif untuk testing
                'priority' => 0,
                'notes' => 'Markup setting alternatif (inactive) untuk testing',
                'created_by' => $adminUser?->id,
                'updated_by' => $adminUser?->id,
            ]);
        }

        $this->command->info("Service markup settings berhasil di-seed untuk {$services->count()} service!");
    }
}
