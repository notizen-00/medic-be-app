<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    public function pharmacy(): HasOne
    {
        return $this->hasOne(Pharmacy::class, 'owner_user_id')->with('profile');
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

    public function pharmacyProducts(): HasManyThrough
    {
        return $this->hasManyThrough(
            Product::class,
            Pharmacy::class,
            'owner_user_id',
            'pharmacy_id',
            'id',
            'id'
        );
    }

    public function pharmacyOrders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            Pharmacy::class,
            'owner_user_id',
            'pharmacy_id',
            'id',
            'id'
        );
    }

    public function courierShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'courier_user_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'partner_services',
            'partner_user_id',
            'service_id'
        )->withPivot(['custom_price', 'coverage_radius_km', 'is_active', 'is_verified', 'notes'])
            ->withTimestamps();
    }

    public function patientServiceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class, 'patient_user_id');
    }

    public function partnerServiceBookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class, 'assigned_partner_user_id');
    }

    public function partnerServices(): HasMany
    {
        return $this->hasMany(PartnerService::class, 'partner_user_id');
    }

    public function balance(): HasOne
    {
        return $this->hasOne(UserBalance::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
