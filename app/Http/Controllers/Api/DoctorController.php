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
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:list,specializations'],
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

        if (($validated['view'] ?? 'list') === 'specializations') {
            return response()->json([
                'message' => 'Daftar spesialisasi dokter berhasil diambil.',
                'data' => $this->doctorDirectoryService->getDoctorSpecializations(),
            ]);
        }

        if (! $request->has('per_page') && isset($validated['limit'])) {
            $request->merge(['per_page' => $validated['limit']]);
        }

        $perPage = $this->resolvePerPage($request);
        $filters = $validated;
        unset($filters['limit']);

        $doctors = $this->paginateCollection(
            $this->doctorDirectoryService->getDoctorList($filters),
            $request,
            $perPage
        );

        return response()->json([
            'message' => 'Daftar dokter berhasil diambil.',
            'data' => $doctors->items(),
            'meta' => [
                'current_page' => $doctors->currentPage(),
                'last_page' => $doctors->lastPage(),
                'per_page' => $doctors->perPage(),
                'total' => $doctors->total(),
            ],
        ]);
    }
}
