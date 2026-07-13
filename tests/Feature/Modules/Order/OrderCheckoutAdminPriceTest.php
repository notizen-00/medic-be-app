<?php

use App\Models\PatientAddress;
use App\Models\Pharmacy;
use App\Models\PharmacyProfile;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('uses admin catalog price instead of selected pharmacy product price during order checkout', function () {
    $patient = User::factory()->create(['role' => 'pasien']);
    $admin = User::factory()->create(['role' => 'admin']);
    $nearOwner = User::factory()->create(['role' => 'mitra']);
    $farOwner = User::factory()->create(['role' => 'mitra']);

    $address = PatientAddress::create([
        'patient_user_id' => $patient->id,
        'label' => 'Rumah',
        'recipient_name' => 'Pasien Test',
        'recipient_phone' => '081234567890',
        'address' => 'Jl. Pasien',
        'latitude' => -8.1700000,
        'longitude' => 113.7000000,
        'is_primary' => true,
    ]);

    $nearPharmacy = new Pharmacy();
    $nearPharmacy->owner_user_id = $nearOwner->id;
    $nearPharmacy->name = 'Apotik Terdekat';
    $nearPharmacy->license_number = 'APT-NEAR';
    $nearPharmacy->address = 'Dekat pasien';
    $nearPharmacy->latitude = -8.1710000;
    $nearPharmacy->longitude = 113.7010000;
    $nearPharmacy->is_active = true;
    $nearPharmacy->save();

    PharmacyProfile::create([
        'pharmacy_id' => $nearPharmacy->id,
        'name' => 'Apotik Terdekat',
        'address' => 'Dekat pasien',
        'latitude' => -8.1710000,
        'longitude' => 113.7010000,
    ]);

    $farPharmacy = new Pharmacy();
    $farPharmacy->owner_user_id = $farOwner->id;
    $farPharmacy->name = 'Apotik Jauh';
    $farPharmacy->license_number = 'APT-FAR';
    $farPharmacy->address = 'Jauh dari pasien';
    $farPharmacy->latitude = -8.3000000;
    $farPharmacy->longitude = 113.9000000;
    $farPharmacy->is_active = true;
    $farPharmacy->save();

    PharmacyProfile::create([
        'pharmacy_id' => $farPharmacy->id,
        'name' => 'Apotik Jauh',
        'address' => 'Jauh dari pasien',
        'latitude' => -8.3000000,
        'longitude' => 113.9000000,
    ]);

    $nearProduct = new Product();
    $nearProduct->pharmacy_id = $nearPharmacy->id;
    $nearProduct->pharmacy_user_id = $nearOwner->id;
    $nearProduct->sku = 'OBT-PARACETAMOL';
    $nearProduct->name = 'Paracetamol';
    $nearProduct->type = 'obat';
    $nearProduct->price = 50000;
    $nearProduct->admin_price = null;
    $nearProduct->cost_price = 9000;
    $nearProduct->stock = 10;
    $nearProduct->track_stock = true;
    $nearProduct->is_active = true;
    $nearProduct->save();

    $farProduct = new Product();
    $farProduct->pharmacy_id = $farPharmacy->id;
    $farProduct->pharmacy_user_id = $farOwner->id;
    $farProduct->sku = 'OBT-PARACETAMOL';
    $farProduct->name = 'Paracetamol';
    $farProduct->type = 'obat';
    $farProduct->price = 15000;
    $farProduct->admin_price = null;
    $farProduct->cost_price = 8000;
    $farProduct->stock = 10;
    $farProduct->track_stock = true;
    $farProduct->is_active = true;
    $farProduct->save();

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/products/{$nearProduct->id}/admin-price", [
        'admin_price' => 12000,
    ])->assertOk()
        ->assertJsonPath('data.sku', 'OBT-PARACETAMOL')
        ->assertJsonPath('data.admin_price', '12000.00')
        ->assertJsonPath('data.updated_count', 2);

    Sanctum::actingAs($patient);

    $this->getJson("/api/patient/products/global?patient_address_id={$address->id}&per_page=10")
        ->assertOk()
        ->assertJsonPath('data.data.0.catalog_price', 12000)
        ->assertJsonPath('data.data.0.lowest_price', 12000)
        ->assertJsonPath('data.data.0.highest_price', 12000)
        ->assertJsonPath('data.data.0.pharmacy_options.0.price', 12000);

    $this->postJson('/api/patient/orders', [
        'patient_user_id' => $patient->id,
        'patient_address_id' => $address->id,
        'order_type' => 'non_resep',
        'items' => [
            [
                'product_id' => $nearProduct->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.pharmacy_id', $nearPharmacy->id)
        ->assertJsonPath('data.subtotal', '24000.00')
        ->assertJsonPath('data.total_amount', '34000.00')
        ->assertJsonPath('data.items.0.unit_price', '12000.00')
        ->assertJsonPath('data.items.0.total_price', '24000.00');

    $this->assertDatabaseHas('order_items', [
        'product_id' => $nearProduct->id,
        'unit_price' => 12000,
        'quantity' => 2,
        'total_price' => 24000,
    ]);

    expect(Product::find($nearProduct->id)->stock)->toBe(8);
});
