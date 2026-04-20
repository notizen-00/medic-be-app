<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'role', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function issueApiToken(string $tokenName = 'user_api_token'): string
    {
        return $this->createToken($tokenName)->plainTextToken;
    }

    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function partnerProfile(): HasOne
    {
        return $this->hasOne(PartnerProfile::class);
    }

    public function courierProfile(): HasOne
    {
        return $this->hasOne(CourierProfile::class);
    }

    public function partnerSchedules(): HasMany
    {
        return $this->hasMany(PartnerSchedule::class, 'partner_user_id');
    }

    public function patientConsultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'patient_user_id');
    }

    public function partnerConsultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'partner_user_id');
    }

    public function sentConsultationMessages(): HasMany
    {
        return $this->hasMany(ConsultationMessage::class, 'sender_user_id');
    }

    public function patientPrescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'patient_user_id');
    }

    public function partnerPrescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'partner_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'patient_user_id');
    }

    public function patientAddresses(): HasMany
    {
        return $this->hasMany(PatientAddress::class, 'patient_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'patient_user_id');
    }

    public function pharmacyProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'pharmacy_user_id');
    }

    public function pharmacyOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'pharmacy_user_id');
    }

    public function courierShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'courier_user_id');
    }
}
