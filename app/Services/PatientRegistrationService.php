<?php

namespace App\Services;

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PatientRegistrationService
{
    public function register(array $payload): User
    {
        return DB::transaction(function () use ($payload) {
            $user = User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'role' => 'pasien',
                'phone' => $payload['phone'],
                'password' => Hash::make($payload['password']),
            ]);

            PatientProfile::create([
                'user_id' => $user->id,
                'date_of_birth' => $payload['date_of_birth'] ?? null,
                'gender' => $payload['gender'] ?? null,
                'address' => $payload['address'] ?? null,
                'blood_type' => $payload['blood_type'] ?? null,
                'emergency_contact_name' => $payload['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $payload['emergency_contact_phone'] ?? null,
                'allergies' => $payload['allergies'] ?? null,
                'medical_notes' => $payload['medical_notes'] ?? null,
            ]);

            return $user->load('patientProfile');
        });
    }
}
