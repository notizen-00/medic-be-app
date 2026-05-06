<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\PharmacyProfile;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PartnerRegistrationService
{
    public function registerDoctor(array $payload): User
    {
        if (! isset($payload['profession'])) {
            $payload['profession'] = 'dokter';
        }

        return $this->registerMitraProfessional($payload);
    }

    public function registerMitraProfessional(array $payload): User
    {
        $profession = $payload['profession'] ?? 'dokter';

        return DB::transaction(function () use ($payload, $profession) {
            $user = User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'role' => 'mitra',
                'phone' => $payload['phone'],
                'password' => Hash::make($payload['password']),
            ]);

            $strPhotoPath = $payload['str_photo_path'] ?? null;
            $ktpPhotoPath = $payload['ktp_photo_path'] ?? null;

            if (($payload['str_photo'] ?? null) instanceof UploadedFile) {
                $strFile = $payload['str_photo'];
                $strPhotoPath = $strFile->storeAs(
                    "partners/{$user->id}",
                    'str.'.strtolower($strFile->getClientOriginalExtension() ?: 'jpg'),
                    'public'
                );
            }

            if (($payload['ktp_photo'] ?? null) instanceof UploadedFile) {
                $ktpFile = $payload['ktp_photo'];
                $ktpPhotoPath = $ktpFile->storeAs(
                    "partners/{$user->id}",
                    'ktp.'.strtolower($ktpFile->getClientOriginalExtension() ?: 'jpg'),
                    'public'
                );
            }

            PartnerProfile::create([
                'user_id' => $user->id,
                'profession' => $profession,
                'specialization' => $payload['specialization'],
                'license_number' => $payload['license_number'],
                'work_location' => $payload['work_location'] ?? null,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'years_of_experience' => $payload['years_of_experience'] ?? 0,
                'consultation_fee' => $payload['consultation_fee'] ?? 0,
                'is_available' => false,
                'bio' => $payload['bio'] ?? null,
                'verification_status' => $payload['verification_status'] ?? 'pending',
                'str_photo_path' => $strPhotoPath,
                'ktp_photo_path' => $ktpPhotoPath,
            ]);

            return $user->load('partnerProfile');
        });
    }

    public function registerPharmacy(User $owner, array $payload): Pharmacy
    {
        return DB::transaction(function () use ($owner, $payload) {
            /** @var User $owner */
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);

            if ($owner->role !== 'mitra') {
                throw new \InvalidArgumentException('Akun pemilik apotik harus role mitra.');
            }

            $pharmacy = Pharmacy::create([
                'owner_user_id' => $owner->id,
                'is_active' => false,
            ]);

            PharmacyProfile::create([
                'pharmacy_id' => $pharmacy->id,
                'name' => $payload['pharmacy_name'],
                'license_number' => $payload['license_number'] ?? null,
                'address' => $payload['work_location'] ?? null,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'opening_time' => $payload['opening_time'] ?? null,
                'closing_time' => $payload['closing_time'] ?? null,
                'description' => $payload['bio'] ?? null,
            ]);

            return $pharmacy->load(['profile', 'owner']);
        });
    }
}
