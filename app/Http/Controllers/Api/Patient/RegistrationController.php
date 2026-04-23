<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Services\PatientRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly PatientRegistrationService $patientRegistrationService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:laki-laki,perempuan'],
            'address' => ['nullable', 'string'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'allergies' => ['nullable', 'string'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        $patient = $this->patientRegistrationService->register($validated);

        return response()->json([
            'message' => 'Pendaftaran pasien berhasil.',
            'data' => $patient,
            'user_api_token' => $patient->issueApiToken('patient_api_token'),
        ], 201);
    }
}
