<?php

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
