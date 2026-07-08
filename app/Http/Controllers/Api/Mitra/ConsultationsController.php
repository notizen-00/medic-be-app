<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Models\ConsultationMessage;
use App\Services\AppNotificationService;
use App\Services\ConsultationPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsultationsController extends Controller
{
    public function __construct(
        private readonly AppNotificationService $notifications,
        private readonly ConsultationPayoutService $consultationPayouts
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,ongoing,completed,cancelled'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $this->resolvePerPage($request);

        $consultations = Consultation::query()
            ->with(['patient', 'partner.partnerProfile', 'payment', 'prescription.items'])
            ->when(
                $validated['status'] ?? null,
                fn($query, $status) => $query->where('status', $status)
            )
            ->when(
                $validated['service_type'] ?? null,
                fn($query, $serviceType) => $query->where('service_type', $serviceType)
            )
            ->when(
                $validated['search'] ?? null,
                fn($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('consultation_code', 'like', "%{$search}%")
                        ->orWhere('complaint', 'like', "%{$search}%")
                        ->orWhere('diagnosis', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn($patientQuery) => $patientQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('partner', fn($partnerQuery) => $partnerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                })
            )
            ->where('partner_user_id', Auth::user()->id)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar semua konsultasi mitra berhasil diambil.',
            'data' => $consultations,
        ]);
    }

    public function show(Request $request, Consultation $consultation): JsonResponse
    {
        $consultation->load(['patient', 'partner.partnerProfile', 'messages.sender', 'payment', 'prescription.items']);

        return response()->json([
            'message' => 'Detail konsultasi mitra berhasil diambil.',
            'data' => $consultation,
        ]);
    }

    public function updateStatus(Request $request, Consultation $consultation): JsonResponse
    {
        $partner = $this->authorizeMitraConsultation($request, $consultation);

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

        $consultation = DB::transaction(function () use ($consultation, $partner, $payload, $validated): Consultation {
            $lockedConsultation = Consultation::query()
                ->with('payment')
                ->lockForUpdate()
                ->findOrFail($consultation->id);

            if ($validated['status'] === 'completed' && (! $lockedConsultation->payment || $lockedConsultation->payment->status !== 'paid')) {
                throw ValidationException::withMessages([
                    'payment' => ['Konsultasi hanya dapat diselesaikan setelah pembayaran lunas.'],
                ]);
            }

            $lockedConsultation->update($payload);

            if ($validated['status'] === 'completed') {
                $this->consultationPayouts->creditPartnerIfNeeded($lockedConsultation, $partner);
            }

            return $lockedConsultation;
        });

        $consultation->load(['patient', 'partner.partnerProfile', 'partnerBalanceTransaction']);

        $this->notifications->send($consultation->patient_user_id, [
            'type' => 'consultation.status_updated',
            'title' => 'Status konsultasi diperbarui',
            'body' => 'Mitra memperbarui status konsultasi menjadi '.$consultation->status.'.',
            'action_url' => '/patient/consultations/'.$consultation->id,
            'reference_type' => 'consultation',
            'reference_id' => $consultation->id,
            'data' => [
                'consultation_id' => $consultation->id,
                'status' => $consultation->status,
            ],
        ]);

        return response()->json([
            'message' => 'Status konsultasi berhasil diperbarui.',
            'data' => $consultation,
        ]);
    }

    public function addMessage(Request $request, Consultation $consultation): JsonResponse
    {
        $user = $this->authorizeMitraConsultation($request, $consultation);

        $validated = $request->validate([
            'message_type' => ['required', 'in:text,image,file,system'],
            'message' => ['nullable', 'string'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);

        $message = ConsultationMessage::create($validated + [
            'consultation_id' => $consultation->id,
            'sender_user_id' => $user->id,
        ]);

        $message->load('sender');

        $this->notifications->send($consultation->patient_user_id, [
            'type' => 'consultation.message_created',
            'title' => 'Pesan konsultasi baru',
            'body' => $user->name.': '.Str::limit($message->message ?? 'Mengirim lampiran', 120),
            'action_url' => '/patient/consultations/'.$consultation->id,
            'reference_type' => 'consultation',
            'reference_id' => $consultation->id,
            'data' => [
                'consultation_id' => $consultation->id,
                'message_id' => $message->id,
                'sender_user_id' => $user->id,
            ],
        ]);

        return response()->json([
            'message' => 'Pesan konsultasi berhasil dikirim.',
            'data' => $message,
        ], 201);
    }

    private function authorizeMitraConsultation(Request $request, Consultation $consultation): User
    {
        $user = $this->ensureAuthenticatedMitra($request);

        if ($consultation->partner_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'consultation' => ['Konsultasi ini tidak berada dalam akses mitra yang sedang login.'],
            ]);
        }

        return $user;
    }

    private function ensureAuthenticatedMitra(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => ['User login tidak ditemukan.'],
            ]);
        }

        if ($user->role !== 'mitra') {
            throw ValidationException::withMessages([
                'user' => ['Endpoint ini hanya dapat diakses oleh mitra.'],
            ]);
        }

        return $user;
    }
}
