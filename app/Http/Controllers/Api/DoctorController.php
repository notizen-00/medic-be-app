<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DoctorDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function __construct(
        private readonly DoctorDirectoryService $doctorDirectoryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'is_available' => ['nullable', 'boolean'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'max_distance_km' => ['nullable', 'numeric', 'min:0'],
        ]);

        $doctors = $this->doctorDirectoryService->getDoctorList($validated);

        return response()->json([
            'message' => 'Daftar dokter berhasil diambil.',
            'data' => $doctors,
        ]);
    }
}
