<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Basic Configuration
    |--------------------------------------------------------------------------
    |
    | Core settings for the API documentation
    |
    */
    'name' => env('APP_NAME', 'Laravel API'),
    'description' => env('API_DESCRIPTION', 'API Documentation'),
    'base_url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Route Filtering Configuration
    |--------------------------------------------------------------------------
    |
    | Define which routes should be included/excluded from documentation
    |
    */
    'routes' => [
        // Base prefix for API routes (e.g. 'api' for routes like 'api/users')
        'prefix' => 'api',

        // Routes to explicitly include
        'include' => [
            // URI patterns to include (supports wildcards)
            'patterns' => [],

            // Only routes with these middleware
            'middleware' => [],

            // Only routes from these controllers
            'controllers' => [],
        ],

        // Routes to explicitly exclude
        'exclude' => [
            // URI patterns to exclude (supports wildcards)
            'patterns' => [],

            // Exclude routes with these middleware
            'middleware' => [],

            // Exclude routes from these controllers
            'controllers' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Structure
    |--------------------------------------------------------------------------
    |
    | How the documentation should be organized in Postman
    |
    */
    'structure' => [
        'folders' => [
            // Grouping strategy: 'prefix', 'nested_path', 'controller'
            'strategy' => 'nested_path',
            'max_depth' => 10, //  when strategy is nested_path

            // Custom name mapping for folders
            'mapping' => [
                // Example: 'admin' => 'Administration'
            ],
        ],

        /**
         * Postman request naming format.
         * Placeholders: {method}, {uri}, {controller}, {action}
         * Example: '[POST] /users' or 'UserController@store'
         */
        'naming_format' => '[{method}] {uri}',

        /**
         * Request body settings:
         * - default_body_type: 'raw' or 'formdata'
         * - default_values: preset values applied to generated request fields
         */
        'requests' => [
            'default_body_type' => 'raw',
            'default_values' => [
                // Seeder-like sample payloads for generated Postman raw JSON.
                'name' => 'Budi Santoso',
                'email' => 'demo@medic-app.test',
                'phone' => '081234567890',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'profession' => 'dokter',
                'specialization' => 'Dokter Umum',
                'license_number' => 'SIP-357801-2026-0001',
                'work_location' => 'Jember, Jawa Timur',
                'pharmacy_name' => 'Apotik Sehat Sentosa',
                'bio' => 'Melayani konsultasi dan homecare area Jember.',

                'latitude' => -8.1724,
                'longitude' => 113.7008,
                'years_of_experience' => 5,
                'consultation_fee' => 75000,
                'coverage_radius_km' => 10,

                'patient_user_id' => 2,
                'partner_user_id' => 3,
                'assigned_partner_user_id' => 3,
                'courier_user_id' => 4,
                'service_id' => 1,
                'patient_address_id' => 1,
                'prescription_id' => 1,

                'service_type' => 'chat',
                'category' => 'Layanan Kesehatan',
                'description' => 'Contoh deskripsi untuk dokumentasi Postman.',
                'base_price' => 100000,
                'custom_price' => 120000,
                'duration_minutes' => 60,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),

                'notes' => 'Contoh catatan dari Postman collection.',
                'complaint' => 'Demam sejak dua hari terakhir.',
                'diagnosis' => 'Observasi awal, butuh pemeriksaan lanjutan.',
                'sender_user_id' => 2,
                'message_type' => 'text',
                'message' => 'Halo dok, saya ingin konsultasi.',
                'attachment_path' => 'consultations/attachments/sample-file.jpg',

                'order_type' => 'non_resep',
                'items' => [
                    [
                        'product_id' => 1,
                        'sku' => 'OBT-PAR-500',
                        'quantity' => 2,
                    ],
                ],
                'items.*.product_id' => 1,
                'items.*.sku' => 'OBT-PAR-500',
                'items.*.quantity' => 2,

                'sku' => 'SKU-OBAT-001',
                'type' => 'obat',
                'price' => 15000,
                'stock' => 25,
                'minimum_stock_alert' => 5,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
                'is_verified' => true,
                'is_homecare' => true,
                'is_available' => true,
                'search' => 'jember',
                'image' => 'products/sample-obat.jpg',

                'status' => 'pending',
                'title' => 'Kurir sedang menuju lokasi',
            ],
            'route_bodies' => [
                'POST api/patient/login' => [
                    'email' => 'pasien@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/patient/service-bookings' => [
                    'service_id' => 1,
                    'patient_user_id' => 2,
                    'patient_address_id' => 1,
                    'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                    'notes' => 'Mohon datang sore hari.',
                ],
                'PATCH api/patient/service-bookings/{serviceBooking}/status' => [
                    'status' => 'confirmed',
                    'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                    'notes' => 'Jadwal sudah dikonfirmasi.',
                ],
                'POST api/patient/consultations' => [
                    'patient_user_id' => 2,
                    'partner_user_id' => 3,
                    'service_type' => 'chat',
                    'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
                    'complaint' => 'Batuk dan pilek sejak 3 hari.',
                    'notes' => 'Mohon konsultasi cepat.',
                ],
                'PATCH api/patient/consultations/{consultation}/status' => [
                    'status' => 'confirmed',
                    'diagnosis' => 'Perlu observasi lebih lanjut.',
                    'notes' => 'Pasien dijadwalkan konsultasi lanjutan.',
                ],
                'POST api/patient/consultations/{consultation}/messages' => [
                    'sender_user_id' => 2,
                    'message_type' => 'text',
                    'message' => 'Dok, apakah obat ini diminum setelah makan?',
                    'attachment_path' => null,
                ],
                'POST api/patient/orders' => [
                    'patient_user_id' => 2,
                    'patient_address_id' => 1,
                    'prescription_id' => null,
                    'order_type' => 'non_resep',
                    'notes' => 'Tolong kirim secepatnya.',
                    'items' => [
                        [
                            'product_id' => 1,
                            'sku' => 'OBT-PAR-500',
                            'quantity' => 2,
                        ],
                    ],
                ],
                'PATCH api/patient/orders/{order}/status' => [
                    'status' => 'confirmed',
                    'notes' => 'Order sudah diproses apotik.',
                ],
                'POST api/mitra/register' => [
                    'name' => 'dr. Andi Pratama',
                    'email' => 'mitra@medic-app.test',
                    'phone' => '081234567891',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'profession' => 'dokter',
                    'specialization' => 'Dokter Umum',
                    'license_number' => 'SIP-MITRA-2026-0001',
                    'work_location' => 'Jember, Jawa Timur',
                    'latitude' => -8.1724,
                    'longitude' => 113.7008,
                    'years_of_experience' => 5,
                    'consultation_fee' => 75000,
                    'bio' => 'Melayani konsultasi dan homecare.',
                ],
                'POST api/mitra/login' => [
                    'email' => 'mitra@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/mitra/doctor/register' => [
                    'name' => 'dr. Sinta Maharani',
                    'email' => 'doctor@medic-app.test',
                    'phone' => '081234567892',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'profession' => 'dokter',
                    'specialization' => 'Dokter Anak',
                    'license_number' => 'SIP-DOC-2026-0001',
                    'work_location' => 'Klinik Tegal Besar, Jember',
                    'latitude' => -8.1861,
                    'longitude' => 113.7178,
                    'years_of_experience' => 7,
                    'consultation_fee' => 100000,
                    'bio' => 'Dokter anak untuk konsultasi online dan kunjungan.',
                ],
                'POST api/mitra/doctor/login' => [
                    'email' => 'doctor@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/mitra/apotik/login' => [
                    'email' => 'apotik@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/mitra/service-applications' => [
                    'service_id' => 1,
                    'custom_price' => 120000,
                    'coverage_radius_km' => 10,
                    'notes' => 'Siap melayani area kota Jember.',
                ],
                'PATCH api/mitra/service-applications/{partnerService}' => [
                    'custom_price' => 135000,
                    'coverage_radius_km' => 12,
                    'is_active' => true,
                    'notes' => 'Update tarif layanan terbaru.',
                ],
                'POST api/mitra/apotik/register' => [
                    'pharmacy_name' => 'Apotik Sehat Sentosa',
                    'license_number' => 'APTK-2026-0001',
                    'work_location' => 'Jl. Gajah Mada No. 10, Jember',
                    'latitude' => -8.1702,
                    'longitude' => 113.7024,
                    'bio' => 'Melayani resep dan obat non resep.',
                ],
                'POST api/mitra/apotik/products' => [
                    'sku' => 'OBT-PAR-500',
                    'name' => 'Paracetamol 500mg',
                    'type' => 'obat',
                    'category' => 'Analgesik',
                    'description' => 'Obat penurun demam dan pereda nyeri.',
                    'price' => 15000,
                    'stock' => 25,
                    'minimum_stock_alert' => 5,
                    'track_stock' => true,
                    'requires_prescription' => false,
                    'is_active' => true,
                    'image' => 'products/paracetamol-500.jpg',
                ],
                'PATCH api/mitra/apotik/products/{product}' => [
                    'name' => 'Paracetamol 500mg Strip',
                    'type' => 'obat',
                    'category' => 'Analgesik',
                    'description' => 'Update nama dan deskripsi produk.',
                    'price' => 17000,
                    'minimum_stock_alert' => 7,
                    'track_stock' => true,
                    'requires_prescription' => false,
                    'is_active' => true,
                    'image' => 'products/paracetamol-500-new.jpg',
                ],
                'PATCH api/mitra/apotik/products/{product}/stock' => [
                    'stock' => 40,
                    'minimum_stock_alert' => 5,
                    'track_stock' => true,
                    'is_active' => true,
                ],
                'PATCH api/mitra/shipments/{shipment}/assign-courier' => [
                    'courier_user_id' => 4,
                ],
                'PATCH api/mitra/shipments/{shipment}/status' => [
                    'status' => 'on_delivery',
                    'title' => 'Pesanan sedang diantar',
                    'description' => 'Kurir sedang menuju alamat pasien.',
                ],
                'POST api/admin/services' => [
                    'name' => 'Homecare Dokter Umum',
                    'service_type' => 'dokter_homecare',
                    'category' => 'Homecare',
                    'description' => 'Layanan kunjungan dokter ke rumah.',
                    'base_price' => 150000,
                    'duration_minutes' => 60,
                    'is_active' => true,
                    'is_homecare' => true,
                ],
                'PATCH api/admin/services/{service}' => [
                    'name' => 'Homecare Dokter Umum Premium',
                    'service_type' => 'dokter_homecare',
                    'category' => 'Homecare',
                    'description' => 'Update layanan homecare dokter.',
                    'base_price' => 175000,
                    'duration_minutes' => 75,
                    'is_active' => true,
                    'is_homecare' => true,
                ],
                'PATCH api/admin/service-applications/{partnerService}/verify' => [
                    'is_verified' => true,
                    'is_active' => true,
                    'notes' => 'Layanan mitra disetujui admin.',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for API authentication documentation and examples
    |
    | Determines how authentication is handled in the generated documentation,
    | including auth type detection, protected route identification, and
    | example values for documentation purposes.
    |
    */
    'auth' => [
        // Enable authentication documentation
        'enabled' => true,

        // Supported: 'bearer', 'basic', 'api_key'
        'type' => 'bearer',

        // Where to send the auth: 'header' or 'query'
        'location' => 'header',

        // Default values (use env vars for real values)
        'default' => [
            'token' => 'your-access-token',       // For bearer auth
            'username' => 'user@example.com',      // For basic auth
            'password' => 'password',              // For basic auth
            'key_name' => 'X-API-KEY',             // For api_key auth
            'key_value' => 'your-api-key-here',    // For api_key auth
        ], 

        // Middleware that indicate protected routes
        'protected_middleware' => ['auth:api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Headers to include with every request
    |
    */
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Where and how to save the generated documentation
    |
    */
    'output' => [
        'driver' => env('POSTMAN_STORAGE_DISK', 'local'),

        // Storage path for generated files
        'path' => env('POSTMAN_STORAGE_DIR', storage_path('postman')),

        // File naming pattern (date will be appended)
        'filename' => env('POSTMAN_STORAGE_FILE', 'api_collection'),
    ],
];
