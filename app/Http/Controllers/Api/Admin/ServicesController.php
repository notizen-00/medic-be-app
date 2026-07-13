<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ServicesController extends Controller
{
    private const SERVICE_TYPES = [
        'consultation',
        'procedure',
        'caregiver',
        'homecare',
        'dokter_homecare',
        'perawat_homecare',
        'bidan_homecare',
        'konsultasi_tindakan',
    ];

    private const SERVICE_MODES = [
        'chat',
        'voice',
        'video',
        'visit',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'service_type' => ['nullable', Rule::in(self::SERVICE_TYPES)],
            'service_mode' => ['nullable', Rule::in(self::SERVICE_MODES)],
            'is_active' => ['nullable', 'boolean'],
            'requires_address' => ['nullable', 'boolean'],
            'requires_schedule' => ['nullable', 'boolean'],
            'requires_matchmaking' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $categoryId = $validated['service_category_id'] ?? $validated['category_id'] ?? null;

        $query = Service::query()
            ->with('serviceCategory')
            ->when($categoryId, fn($query) => $query->where('service_category_id', $categoryId))
            ->when(isset($validated['service_type']), fn($query) => $query->where('service_type', $validated['service_type']))
            ->when(isset($validated['service_mode']), fn($query) => $query->where('service_mode', $validated['service_mode']))
            ->when(array_key_exists('is_active', $validated), fn($query) => $query->where('is_active', $validated['is_active']))
            ->when(array_key_exists('requires_address', $validated), fn($query) => $query->where('requires_address', $validated['requires_address']))
            ->when(array_key_exists('requires_schedule', $validated), fn($query) => $query->where('requires_schedule', $validated['requires_schedule']))
            ->when(array_key_exists('requires_matchmaking', $validated), fn($query) => $query->where('requires_matchmaking', $validated['requires_matchmaking']))
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $query->where(function ($query) use ($validated) {
                    $query->where('name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('service_code', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('slug', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('category', 'like', '%' . $validated['search'] . '%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        return response()->json([
            'message' => 'Daftar master layanan berhasil diambil.',
            'data' => $query->paginate($validated['per_page'] ?? 20),
        ]);
    }

    public function show(Service $service): JsonResponse
    {
        $service->load(['serviceCategory', 'partnerServices.partner.partnerProfile']);

        return response()->json([
            'message' => 'Detail master layanan berhasil diambil.',
            'data' => $service,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $imagePath = $this->resolveServiceImage($request, $validated);

        $service = Service::create([
            'service_code' => $validated['service_code'] ?? $this->generateServiceCode(),
            'service_category_id' => $validated['service_category_id'] ?? $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'service_type' => $validated['service_type'],
            'service_mode' => $validated['service_mode'] ?? 'visit',
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'base_price' => $validated['base_price'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'requires_address' => $validated['requires_address'] ?? true,
            'requires_schedule' => $validated['requires_schedule'] ?? false,
            'requires_matchmaking' => $validated['requires_matchmaking'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'is_homecare' => $validated['is_homecare'] ?? (($validated['service_mode'] ?? 'visit') === 'visit'),
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Master layanan berhasil dibuat.',
            'data' => $service->load('serviceCategory'),
        ], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        // PHP hanya menjamin parsing upload multipart ke UploadedFile pada request POST.
        // Route POST /services/{service} disediakan khusus update yang membawa image.
        $validated = $request->validate($this->rules($service));

        if (array_key_exists('category_id', $validated) && ! array_key_exists('service_category_id', $validated)) {
            $validated['service_category_id'] = $validated['category_id'];
        }

        unset($validated['category_id']);

        if (array_key_exists('name', $validated) && empty($validated['slug']) && blank($service->slug)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if ($request->hasFile('image')) {
            $this->deleteServiceImage($service);
            $validated['image'] = $request->file('image')->store('services', 'public');
        } else {
            unset($validated['image']);
        }

        if ($request->boolean('remove_image')) {
            $this->deleteServiceImage($service);
            $validated['image'] = null;
        }

        unset($validated['remove_image'], $validated['image_path']);

        $service->update($validated);

        return response()->json([
            'message' => 'Master layanan berhasil diperbarui.',
            'data' => $service->refresh()->load('serviceCategory'),
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->partnerServices()->exists() || $service->bookings()->exists()) {
            return response()->json([
                'message' => 'Master layanan masih dipakai oleh mitra atau booking dan tidak bisa dihapus.',
            ], 422);
        }

        $service->delete();

        return response()->json([
            'message' => 'Master layanan berhasil dihapus.',
        ]);
    }

    private function rules(?Service $service = null): array
    {
        $required = $service ? 'sometimes' : 'required';
        $serviceId = $service?->id;

        return [
            'service_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('services', 'service_code')->ignore($serviceId),
            ],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'service_type' => [$required, Rule::in(self::SERVICE_TYPES)],
            'service_mode' => ['nullable', Rule::in(self::SERVICE_MODES)],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'base_price' => [$required, 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'requires_address' => ['nullable', 'boolean'],
            'requires_schedule' => ['nullable', 'boolean'],
            'requires_matchmaking' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_homecare' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    private function generateServiceCode(): string
    {
        do {
            $code = 'SRV-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        } while (Service::where('service_code', $code)->exists());

        return $code;
    }


    private function resolveServiceImage(Request $request, array $validated, ?Service $service = null): ?string
    {
        if ($validated['remove_image'] ?? false) {
            $this->deleteServiceImage($service);
            return null;
        }

        if ($request->hasFile('image')) {
            $this->deleteServiceImage($service);

            return $request->file('image')->store('services', 'public');
        }

        return $validated['image_path'] ?? $service?->image;
    }

    private function deleteServiceImage(?Service $service): void
    {
        if (! $service?->image) {
            return;
        }

        if (! str_starts_with($service->image, 'http://') && ! str_starts_with($service->image, 'https://')) {
            Storage::disk('public')->delete($service->image);
        }
    }
}
