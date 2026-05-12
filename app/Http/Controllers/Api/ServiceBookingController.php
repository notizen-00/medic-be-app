<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientAddress;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Services\ServicePartnerSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceBookingController extends Controller
{
    public function __construct(
        private readonly ServicePartnerSelectionService $servicePartnerSelectionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'status' => ['nullable', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $bookings = ServiceBooking::query()
            ->with(['service', 'patient', 'assignedPartner.partnerProfile', 'address'])
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientId) => $query->where('patient_user_id', $patientId)
            )
            ->when(
                $validated['assigned_partner_user_id'] ?? null,
                fn ($query, $partnerId) => $query->where('assigned_partner_user_id', $partnerId)
            )
            ->when(
                $validated['service_id'] ?? null,
                fn ($query, $serviceId) => $query->where('service_id', $serviceId)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar booking layanan berhasil diambil.',
            'data' => $bookings,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'patient_user_id' => ['required', 'integer', 'exists:users,id'],
            'patient_address_id' => ['nullable', 'integer', 'exists:patient_addresses,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureBookingCanBeCreatedByAuthenticatedUser($request, (int) $validated['patient_user_id']);

        $service = Service::query()
            ->with('partnerServices.partner.partnerProfile')
            ->findOrFail($validated['service_id']);

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'service_id' => ['Layanan yang dipilih sedang tidak aktif.'],
            ]);
        }

        if ($service->is_homecare && ! isset($validated['patient_address_id'])) {
            throw ValidationException::withMessages([
                'patient_address_id' => ['Alamat pasien wajib diisi untuk layanan homecare.'],
            ]);
        }

        $address = isset($validated['patient_address_id'])
            ? PatientAddress::find($validated['patient_address_id'])
            : null;

        $selectedPartnerService = $this->servicePartnerSelectionService
            ->resolveNearestPartnerForBooking($service, $address);

        $booking = ServiceBooking::create([
            'booking_code' => 'SVB-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'service_id' => $service->id,
            'patient_user_id' => $validated['patient_user_id'],
            'assigned_partner_user_id' => $selectedPartnerService->partner_user_id,
            'patient_address_id' => $validated['patient_address_id'] ?? null,
            'status' => 'pending',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'total_amount' => $selectedPartnerService->custom_price ?? $service->base_price,
            'notes' => $validated['notes'] ?? null,
        ]);

        $booking->load(['service', 'patient', 'assignedPartner.partnerProfile', 'address']);

        return response()->json([
            'message' => 'Booking layanan berhasil dibuat.',
            'data' => $booking,
        ], 201);
    }

    public function show(ServiceBooking $serviceBooking): JsonResponse
    {
        $serviceBooking->load(['service', 'patient', 'assignedPartner.partnerProfile', 'address']);

        return response()->json([
            'message' => 'Detail booking layanan berhasil diambil.',
            'data' => $serviceBooking,
        ]);
    }

    public function updateStatus(Request $request, ServiceBooking $serviceBooking): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,scheduled,on_the_way,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureBookingCanBeUpdatedByAuthenticatedUser($request, $serviceBooking);

        $payload = [
            'status' => $validated['status'],
            'scheduled_at' => $validated['scheduled_at'] ?? $serviceBooking->scheduled_at,
            'notes' => $validated['notes'] ?? $serviceBooking->notes,
        ];

        if ($validated['status'] === 'on_the_way' && $serviceBooking->started_at === null) {
            $payload['started_at'] = now();
        }

        if (in_array($validated['status'], ['completed', 'cancelled'], true) && $serviceBooking->completed_at === null) {
            $payload['completed_at'] = now();
        }

        $serviceBooking->update($payload);
        $serviceBooking->load(['service', 'patient', 'assignedPartner.partnerProfile', 'address']);

        return response()->json([
            'message' => 'Status booking layanan berhasil diperbarui.',
            'data' => $serviceBooking,
        ]);
    }

    private function ensureBookingCanBeCreatedByAuthenticatedUser(Request $request, int $patientUserId): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if ($user->id !== $patientUserId && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'patient_user_id' => ['Booking layanan hanya dapat dibuat untuk akun pasien yang sedang login.'],
            ]);
        }
    }

    private function ensureBookingCanBeUpdatedByAuthenticatedUser(Request $request, ServiceBooking $serviceBooking): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if (! in_array($user->id, [$serviceBooking->patient_user_id, $serviceBooking->assigned_partner_user_id], true) && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Anda tidak memiliki akses untuk memperbarui booking layanan ini.'],
            ]);
        }
    }
}
