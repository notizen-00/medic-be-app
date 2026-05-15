<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceBookingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $bookings = ServiceBooking::query()
            ->with(['service', 'patient', 'assignedPartner.partnerProfile', 'address', 'histories.actor', 'partnerBalanceTransaction'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $validated['service_id'] ?? null,
                fn ($query, $serviceId) => $query->where('service_id', $serviceId)
            )
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientUserId) => $query->where('patient_user_id', $patientUserId)
            )
            ->when(
                $validated['assigned_partner_user_id'] ?? null,
                fn ($query, $partnerUserId) => $query->where('assigned_partner_user_id', $partnerUserId)
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('booking_code', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patientQuery) => $patientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('assignedPartner', fn ($partnerQuery) => $partnerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery
                            ->where('name', 'like', "%{$search}%"));
                })
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar semua booking layanan admin berhasil diambil.',
            'data' => $bookings,
        ]);
    }
}
