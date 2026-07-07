<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientMemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relationship' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $members = PatientMember::query()
            ->where('owner_user_id', $request->user()->id)
            ->when(
                $validated['relationship'] ?? null,
                fn ($query, $relationship) => $query->where('relationship', $relationship)
            )
            ->when(
                $validated['search'] ?? null,
                fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('relationship', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                })
            )
            ->latest('is_primary')
            ->latest()
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar profil pasien berhasil diambil.',
            'data' => $members,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $validated['owner_user_id'] = $request->user()->id;

        $member = DB::transaction(function () use ($request, $validated): PatientMember {
            if (($validated['is_primary'] ?? false) === true) {
                $this->clearPrimaryMember($request->user()->id);
            }

            $member = PatientMember::create($validated);

            if (! PatientMember::where('owner_user_id', $request->user()->id)->where('id', '!=', $member->id)->exists()) {
                $member->update(['is_primary' => true]);
            }

            return $member->fresh();
        });

        return response()->json([
            'message' => 'Profil pasien berhasil dibuat.',
            'data' => $member,
        ], 201);
    }

    public function show(Request $request, PatientMember $patientMember): JsonResponse
    {
        $this->ensureOwner($request, $patientMember);

        return response()->json([
            'message' => 'Detail profil pasien berhasil diambil.',
            'data' => $patientMember,
        ]);
    }

    public function update(Request $request, PatientMember $patientMember): JsonResponse
    {
        $this->ensureOwner($request, $patientMember);
        $validated = $this->validatedPayload($request, partial: true);

        DB::transaction(function () use ($request, $patientMember, $validated): void {
            if (($validated['is_primary'] ?? false) === true) {
                $this->clearPrimaryMember($request->user()->id);
            }

            $patientMember->update($validated);
        });

        return response()->json([
            'message' => 'Profil pasien berhasil diperbarui.',
            'data' => $patientMember->fresh(),
        ]);
    }

    public function destroy(Request $request, PatientMember $patientMember): JsonResponse
    {
        $this->ensureOwner($request, $patientMember);
        $patientMember->delete();

        return response()->json([
            'message' => 'Profil pasien berhasil dihapus.',
        ]);
    }

    public function setPrimary(Request $request, PatientMember $patientMember): JsonResponse
    {
        $this->ensureOwner($request, $patientMember);

        DB::transaction(function () use ($request, $patientMember): void {
            $this->clearPrimaryMember($request->user()->id);
            $patientMember->update(['is_primary' => true]);
        });

        return response()->json([
            'message' => 'Profil pasien utama berhasil diperbarui.',
            'data' => $patientMember->fresh(),
        ]);
    }

    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullable = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'relationship' => [$nullable, 'nullable', 'string', 'max:50'],
            'date_of_birth' => [$nullable, 'nullable', 'date'],
            'age' => [$nullable, 'nullable', 'integer', 'min:0', 'max:150'],
            'gender' => [$nullable, 'nullable', 'in:laki-laki,perempuan'],
            'phone' => [$nullable, 'nullable', 'string', 'max:20'],
            'blood_type' => [$nullable, 'nullable', 'string', 'max:5'],
            'emergency_contact_name' => [$nullable, 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => [$nullable, 'nullable', 'string', 'max:20'],
            'allergies' => [$nullable, 'nullable', 'string'],
            'medical_notes' => [$nullable, 'nullable', 'string'],
            'address_label' => [$nullable, 'nullable', 'string', 'max:255'],
            'recipient_name' => [$nullable, 'nullable', 'string', 'max:255'],
            'recipient_phone' => [$nullable, 'nullable', 'string', 'max:20'],
            'address' => [$nullable, 'nullable', 'string'],
            'province' => [$nullable, 'nullable', 'string', 'max:255'],
            'city' => [$nullable, 'nullable', 'string', 'max:255'],
            'district' => [$nullable, 'nullable', 'string', 'max:255'],
            'postal_code' => [$nullable, 'nullable', 'string', 'max:10'],
            'latitude' => [$nullable, 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$nullable, 'nullable', 'numeric', 'between:-180,180'],
            'is_primary' => [$nullable, 'boolean'],
        ]);
    }

    private function ensureOwner(Request $request, PatientMember $patientMember): void
    {
        if ($patientMember->owner_user_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'patient_member' => ['Profil pasien ini tidak berada dalam akun yang sedang login.'],
            ]);
        }
    }

    private function clearPrimaryMember(int $ownerUserId): void
    {
        PatientMember::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
