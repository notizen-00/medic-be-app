<?php

namespace App\Services;

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PartnerRegistrationService
{
    public function registerDoctor(array $payload): User
    {
        return DB::transaction(function () use ($payload) {
            $user = User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'role' => 'dokter',
                'phone' => $payload['phone'],
                'password' => Hash::make($payload['password']),
            ]);

            PartnerProfile::create([
                'user_id' => $user->id,
                'profession' => 'dokter',
                'specialization' => $payload['specialization'],
                'license_number' => $payload['license_number'],
                'work_location' => $payload['work_location'] ?? null,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'years_of_experience' => $payload['years_of_experience'] ?? 0,
                'consultation_fee' => $payload['consultation_fee'] ?? 0,
                'is_available' => false,
                'bio' => $payload['bio'] ?? null,
            ]);

            return $user->load('partnerProfile');
        });
    }

    public function registerPharmacy(array $payload): User
    {
        return DB::transaction(function () use ($payload) {
            $user = User::create([
                'name' => $payload['owner_name'] ?? $payload['pharmacy_name'],
                'email' => $payload['email'],
                'role' => 'apotik',
                'phone' => $payload['phone'],
                'password' => Hash::make($payload['password']),
            ]);

            PartnerProfile::create([
                'user_id' => $user->id,
                'profession' => 'apotik',
                'pharmacy_name' => $payload['pharmacy_name'],
                'specialization' => $payload['specialization'] ?? 'Apotek dan Penjualan Produk Kesehatan',
                'license_number' => $payload['license_number'],
                'work_location' => $payload['work_location'] ?? null,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'years_of_experience' => $payload['years_of_experience'] ?? 0,
                'consultation_fee' => 0,
                'is_available' => false,
                'bio' => $payload['bio'] ?? null,
            ]);

            return $user->load('partnerProfile');
        });
    }
}
