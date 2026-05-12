<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,ongoing,completed,cancelled'],
            'partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $consultations = Consultation::query()
            ->with(['patient', 'partner.partnerProfile', 'payment', 'prescription.items'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $validated['partner_user_id'] ?? null,
                fn ($query, $partnerUserId) => $query->where('partner_user_id', $partnerUserId)
            )
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientUserId) => $query->where('patient_user_id', $patientUserId)
            )
            ->when(
                $validated['service_type'] ?? null,
                fn ($query, $serviceType) => $query->where('service_type', $serviceType)
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('consultation_code', 'like', "%{$search}%")
                        ->orWhere('complaint', 'like', "%{$search}%")
                        ->orWhere('diagnosis', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                })
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar semua konsultasi admin berhasil diambil.',
            'data' => $consultations,
        ]);
    }

    public function show(Request $request, Consultation $consultation): JsonResponse
    {
        $consultation->load(['patient', 'partner.partnerProfile', 'messages.sender', 'payment', 'prescription.items']);

        return response()->json([
            'message' => 'Detail konsultasi admin berhasil diambil.',
            'data' => $consultation,
        ]);
    }
}
