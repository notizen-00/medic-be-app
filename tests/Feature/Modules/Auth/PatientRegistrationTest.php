<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a patient and creates the default patient records', function () {
    $response = $this->postJson('/api/patient/register', [
        'name' => 'Pasien Modul Test',
        'email' => 'pasien.module@example.test',
        'phone' => '081234567001',
        'password' => 'password',
        'password_confirmation' => 'password',
        'date_of_birth' => '1995-05-12',
        'gender' => 'perempuan',
        'address' => 'Jl. Testing No. 1',
        'blood_type' => 'O',
    ], apiHeaders());

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Pendaftaran pasien berhasil.')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'role',
                'patient_profile',
                'patient_members',
            ],
            'user_api_token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'pasien.module@example.test',
        'role' => 'pasien',
    ]);

    $this->assertDatabaseHas('patient_profiles', [
        'gender' => 'perempuan',
        'blood_type' => 'O',
    ]);

    $this->assertDatabaseHas('patient_members', [
        'name' => 'Pasien Modul Test',
        'relationship' => 'self',
        'is_primary' => true,
    ]);
});

it('resets old api tokens when a patient logs in again', function () {
    User::factory()->create([
        'name' => 'Pasien Token Reset',
        'email' => 'pasien.token-reset@example.test',
        'phone' => '081234567002',
        'role' => 'pasien',
        'password' => Hash::make('password'),
    ]);

    $firstLogin = $this->postJson('/api/patient/login', [
        'email' => 'pasien.token-reset@example.test',
        'password' => 'password',
    ]);

    $firstToken = $firstLogin->json('user_api_token');

    $secondLogin = $this->postJson('/api/patient/login', [
        'email' => 'pasien.token-reset@example.test',
        'password' => 'password',
    ]);

    $secondToken = $secondLogin->json('user_api_token');

    expect($firstToken)->not->toBe($secondToken);

    $this->withHeader('Authorization', "Bearer {$firstToken}")
        ->getJson('/api/shared/me')
        ->assertUnauthorized();

    $this->withHeader('Authorization', "Bearer {$secondToken}")
        ->getJson('/api/shared/me')
        ->assertOk();
});
