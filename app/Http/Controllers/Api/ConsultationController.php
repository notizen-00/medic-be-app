<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\PartnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConsultationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'partner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,confirmed,ongoing,completed,cancelled'],
        ]);

        $consultations = Consultation::query()
            ->with(['patient', 'partner.partnerProfile', 'messages.sender', 'prescription.items', 'payment'])
            ->when(
                $validated['patient_user_id'] ?? null,
                fn ($query, $patientId) => $query->where('patient_user_id', $patientId)
            )
            ->when(
                $validated['partner_user_id'] ?? null,
                fn ($query, $partnerId) => $query->where('partner_user_id', $partnerId)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar konsultasi berhasil diambil.',
            'data' => $consultations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_user_id' => ['required', 'integer', 'exists:users,id'],
            'partner_user_id' => ['required', 'integer', 'exists:users,id'],
            'service_type' => ['required', 'in:chat,voice_call,video_call,visit'],
            'scheduled_at' => ['nullable', 'date'],
            'complaint' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $partnerProfile = PartnerProfile::where('user_id', $validated['partner_user_id'])->first();

        if (! $partnerProfile || $partnerProfile->profession !== 'dokter') {
            throw ValidationException::withMessages([
                'partner_user_id' => ['Konsultasi hanya dapat dibuat dengan mitra dokter.'],
            ]);
        }

        $consultation = Consultation::create([
            'consultation_code' => 'KONS-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'patient_user_id' => $validated['patient_user_id'],
            'partner_user_id' => $validated['partner_user_id'],
            'service_type' => $validated['service_type'],
            'status' => 'pending',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'complaint' => $validated['complaint'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'consultation_fee' => $partnerProfile?->consultation_fee ?? 0,
        ]);

        $consultation->load(['patient', 'partner.partnerProfile']);

        return response()->json([
            'message' => 'Konsultasi berhasil dibuat.',
            'data' => $consultation,
        ], 201);
    }

    public function show(Consultation $consultation): JsonResponse
    {
        $consultation->load(['patient', 'partner.partnerProfile', 'messages.sender', 'prescription.items', 'payment']);

        return response()->json([
            'message' => 'Detail konsultasi berhasil diambil.',
            'data' => $consultation,
        ]);
    }

    public function updateStatus(Request $request, Consultation $consultation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,confirmed,ongoing,completed,cancelled'],
            'diagnosis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'status' => $validated['status'],
            'diagnosis' => $validated['diagnosis'] ?? $consultation->diagnosis,
            'notes' => $validated['notes'] ?? $consultation->notes,
        ];

        if ($validated['status'] === 'ongoing' && $consultation->started_at === null) {
            $payload['started_at'] = now();
        }

        if (in_array($validated['status'], ['completed', 'cancelled'], true) && $consultation->ended_at === null) {
            $payload['ended_at'] = now();
        }

        $consultation->update($payload);
        $consultation->load(['patient', 'partner.partnerProfile']);

        return response()->json([
            'message' => 'Status konsultasi berhasil diperbarui.',
            'data' => $consultation,
        ]);
    }

    public function addMessage(Request $request, Consultation $consultation): JsonResponse
    {
        $validated = $request->validate([
            'sender_user_id' => ['required', 'integer', 'exists:users,id'],
            'message_type' => ['required', 'in:text,image,file,system'],
            'message' => ['nullable', 'string'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);

        $message = ConsultationMessage::create($validated + [
            'consultation_id' => $consultation->id,
        ]);

        $message->load('sender');

        return response()->json([
            'message' => 'Pesan konsultasi berhasil dikirim.',
            'data' => $message,
        ], 201);
    }
}
