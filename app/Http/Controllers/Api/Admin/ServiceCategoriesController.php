<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceCategoriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ServiceCategory::query()
            ->withCount('services')
            ->when(isset($validated['search']), function ($query) use ($validated) {
                $query->where(function ($query) use ($validated) {
                    $query->where('name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('slug', 'like', '%' . $validated['search'] . '%');
                });
            })
            ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
            ->orderBy('sort_order')
            ->orderBy('name');

        return response()->json([
            'message' => 'Daftar kategori layanan berhasil diambil.',
            'data' => $query->paginate($validated['per_page'] ?? 20),
        ]);
    }

    public function show(ServiceCategory $serviceCategory): JsonResponse
    {
        $serviceCategory->load(['services' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')]);

        return response()->json([
            'message' => 'Detail kategori layanan berhasil diambil.',
            'data' => $serviceCategory,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:service_categories,slug'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $serviceCategory = ServiceCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Kategori layanan berhasil dibuat.',
            'data' => $serviceCategory,
        ], 201);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('service_categories', 'slug')->ignore($serviceCategory->id),
            ],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated) && empty($validated['slug']) && blank($serviceCategory->slug)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $serviceCategory->update($validated);

        return response()->json([
            'message' => 'Kategori layanan berhasil diperbarui.',
            'data' => $serviceCategory->refresh(),
        ]);
    }

    public function destroy(ServiceCategory $serviceCategory): JsonResponse
    {
        if ($serviceCategory->services()->exists()) {
            return response()->json([
                'message' => 'Kategori layanan masih memiliki service dan tidak bisa dihapus.',
            ], 422);
        }

        $serviceCategory->delete();

        return response()->json([
            'message' => 'Kategori layanan berhasil dihapus.',
        ]);
    }
}
