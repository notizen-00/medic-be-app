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

        foreach ($services as $index => $service) {
            $isPercentage = $index % 2 === 0;

            ServiceMarkupSetting::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'priority' => 1,
                    'notes' => "Markup setting default untuk service {$service->name}",
                ],
                [
                    'markup_type' => $isPercentage ? 'percentage' : 'fixed',
                    'markup_value' => $isPercentage ? 10 + ($index * 2) : 10000 + ($index * 5000),
                    'min_final_price' => null,
                    'is_active' => true,
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]
            );
        }

        if ($services->count() >= 1) {
            ServiceMarkupSetting::updateOrCreate(
                [
                    'service_id' => $services[0]->id,
                    'priority' => 0,
                    'notes' => 'Markup setting alternatif (inactive) untuk testing',
                ],
                [
                    'markup_type' => 'percentage',
                    'markup_value' => 25,
                    'min_final_price' => 50000,
                    'is_active' => false,
                    'created_by' => $adminUser?->id,
                    'updated_by' => $adminUser?->id,
                ]
            );
        }

        $this->command->info("Service markup settings berhasil di-seed untuk {$services->count()} service!");
    }
}
