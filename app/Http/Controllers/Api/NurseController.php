<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DoctorDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NurseController extends Controller
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);
        $filters = $validated;
        unset($filters['limit']);

        $nurses = $this->paginateCollection(
            $this->doctorDirectoryService->getNurseList($filters),
            $request,
            $perPage
        );

        return response()->json([
            'message' => 'Daftar perawat berhasil diambil.',
            'data' => $nurses->items(),
            'meta' => [
                'current_page' => $nurses->currentPage(),
                'last_page' => $nurses->lastPage(),
                'per_page' => $nurses->perPage(),
                'total' => $nurses->total(),
            ],
        ]);
    }
}
