<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets admin manage service categories and services', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'phone' => '080000000001',
    ]);

    Sanctum::actingAs($admin);

    $categoryResponse = $this->postJson('/api/admin/service-categories', [
        'name' => 'Emergency Care',
        'slug' => 'emergency-care',
        'icon' => 'siren',
        'sort_order' => 5,
        'is_active' => true,
    ], apiHeaders());

    $categoryResponse
        ->assertCreated()
        ->assertJsonPath('data.slug', 'emergency-care');

    $categoryId = $categoryResponse->json('data.id');

    $serviceResponse = $this->postJson('/api/admin/services', [
        'service_category_id' => $categoryId,
        'service_code' => 'SRV-TEST-001',
        'name' => 'Visit Dokter Darurat',
        'slug' => 'visit-dokter-darurat',
        'service_type' => 'homecare',
        'service_mode' => 'visit',
        'category' => 'Emergency',
        'description' => 'Kunjungan dokter untuk kondisi darurat ringan.',
        'base_price' => 300000,
        'duration_minutes' => 90,
        'requires_address' => true,
        'requires_schedule' => true,
        'requires_matchmaking' => true,
        'sort_order' => 10,
        'is_active' => true,
        'is_homecare' => true,
    ], apiHeaders());

    $serviceResponse
        ->assertCreated()
        ->assertJsonPath('data.service_code', 'SRV-TEST-001')
        ->assertJsonPath('data.service_category.slug', 'emergency-care');

    $serviceId = $serviceResponse->json('data.id');

    $this->patchJson("/api/admin/services/{$serviceId}", [
        'base_price' => 325000,
        'requires_schedule' => false,
    ], apiHeaders())
        ->assertOk()
        ->assertJsonPath('data.base_price', '325000.00')
        ->assertJsonPath('data.requires_schedule', false);

    $this->getJson('/api/admin/services?category_id=' . $categoryId, apiHeaders())
        ->assertOk()
        ->assertJsonPath('data.data.0.service_code', 'SRV-TEST-001');

    $this->deleteJson("/api/admin/services/{$serviceId}", [], apiHeaders())
        ->assertOk();

    expect(Service::whereKey($serviceId)->exists())->toBeFalse();

    $this->deleteJson("/api/admin/service-categories/{$categoryId}", [], apiHeaders())
        ->assertOk();

    expect(ServiceCategory::whereKey($categoryId)->exists())->toBeFalse();
});

it('blocks non admin users from admin service crud', function () {
    $user = User::factory()->create([
        'role' => 'pasien',
        'phone' => '080000000002',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/admin/services', apiHeaders())
        ->assertForbidden();
});
