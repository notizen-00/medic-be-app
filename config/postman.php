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
                'amount' => 100000,
                'payment_method' => 'midtrans',
                'transaction_uuid' => 'topup-transaction-uuid',
                'reference_type' => 'order',
                'reference_id' => 1,
                'code' => 'SEHAT10',
                'promo_code' => 'SEHAT10',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'min_purchase' => 50000,
                'max_discount' => 25000,
                'max_uses' => 100,
                'max_uses_per_user' => 1,
                'valid_from' => now()->format('Y-m-d'),
                'valid_until' => now()->addMonth()->format('Y-m-d'),
                'markup_type' => 'percentage',
                'markup_value' => 10,
                'min_final_price' => 50000,
                'priority' => 1,
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
                'summary' => 'Layanan selesai, pasien sudah mendapatkan penanganan.',
                'handled_at' => now()->format('Y-m-d H:i:s'),
                'meta' => [
                    'vital_signs' => [
                        'blood_pressure' => '120/80',
                        'temperature' => 36.8,
                    ],
                ],
                'order_id' => 'PAY-KONS-202605150001',
                'status_code' => '200',
                'gross_amount' => '100000.00',
                'signature_key' => 'generated-midtrans-signature',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'payment_type' => 'bank_transfer',
                'settlement_time' => now()->format('Y-m-d H:i:s'),
                'transaction_time' => now()->format('Y-m-d H:i:s'),
            ],
            'route_bodies' => [
                'POST api/patient/register' => [
                    'name' => 'Budi Santoso',
                    'email' => 'pasien.baru@medic-app.test',
                    'phone' => '081234567899',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'date_of_birth' => '1998-08-17',
                    'gender' => 'laki-laki',
                    'address' => 'Jl. Mawar No. 12, Jember',
                    'blood_type' => 'O',
                    'emergency_contact_name' => 'Siti Santoso',
                    'emergency_contact_phone' => '081234567800',
                    'allergies' => 'Debu dan udang.',
                    'medical_notes' => 'Riwayat asma ringan.',
                ],
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
                'POST api/mitra/nurse/register' => [
                    'name' => 'Ns. Rina Lestari',
                    'email' => 'nurse@medic-app.test',
                    'phone' => '081234567893',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                    'specialization' => 'Perawat Homecare',
                    'license_number' => 'SIP-NRS-2026-0001',
                    'work_location' => 'Patrang, Jember',
                    'latitude' => -8.1592,
                    'longitude' => 113.7151,
                    'years_of_experience' => 4,
                    'consultation_fee' => 65000,
                    'bio' => 'Perawat homecare untuk rawat luka dan observasi pasien.',
                    'str_photo' => null,
                    'ktp_photo' => null,
                ],
                'POST api/mitra/nurse/login' => [
                    'email' => 'nurse@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/mitra/apotik/login' => [
                    'email' => 'apotik@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/admin/login' => [
                    'email' => 'admin@medic-app.test',
                    'password' => 'password123',
                ],
                'POST api/midtrans/callback' => [
                    'order_id' => 'PAY-KONS-202605150001',
                    'status_code' => '200',
                    'gross_amount' => '100000.00',
                    'signature_key' => 'generated-midtrans-signature',
                    'transaction_status' => 'settlement',
                    'fraud_status' => 'accept',
                    'payment_type' => 'bank_transfer',
                    'settlement_time' => now()->format('Y-m-d H:i:s'),
                    'transaction_time' => now()->format('Y-m-d H:i:s'),
                ],
                'PATCH api/patient/consultations/{consultation}/pay' => [
                    'notes' => 'Pembayaran melalui Midtrans Snap.',
                ],
                'POST api/patient/balance/topup' => [
                    'amount' => 100000,
                    'payment_method' => 'midtrans',
                ],
                'PATCH api/patient/balance/topup/confirm' => [
                    'transaction_uuid' => 'topup-transaction-uuid',
                    'status' => 'success',
                ],
                'POST api/patient/service-bookings/check-promo-code' => [
                    'code' => 'SEHAT10',
                    'service_id' => 1,
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
                    'opening_time' => '08:00',
                    'closing_time' => '21:00',
                    'bio' => 'Melayani resep dan obat non resep.',
                ],
                'PATCH api/mitra/profile' => [
                    'specialization' => 'Dokter Umum',
                    'license_number' => 'SIP-MITRA-2026-0002',
                    'work_location' => 'Jember, Jawa Timur',
                    'latitude' => -8.1724,
                    'longitude' => 113.7008,
                    'years_of_experience' => 6,
                    'consultation_fee' => 80000,
                    'bio' => 'Update profil mitra aktif.',
                    'is_available' => true,
                    'str_photo' => null,
                    'ktp_photo' => null,
                ],
                'PATCH api/mitra/service-bookings/{serviceBooking}/status' => [
                    'status' => 'on_the_way',
                    'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                    'notes' => 'Mitra sedang menuju lokasi pasien.',
                ],
                'PATCH api/mitra/service-bookings/{serviceBooking}/accept' => [
                    'notes' => 'Pesanan diterima dan mitra siap menangani pasien.',
                ],
                'PATCH api/mitra/service-bookings/{serviceBooking}/start-journey' => [
                    'notes' => 'Mitra berangkat dari lokasi praktik menuju rumah pasien.',
                ],
                'POST api/mitra/service-bookings/{serviceBooking}/histories' => [
                    'title' => 'Pemasangan infus',
                    'description' => 'Perawat melakukan pemasangan infus dan monitoring awal kondisi pasien.',
                    'handled_at' => now()->format('Y-m-d H:i:s'),
                    'meta' => [
                        'vital_signs' => [
                            'blood_pressure' => '120/80',
                            'temperature' => 36.8,
                        ],
                        'materials' => ['infus set', 'cairan NaCl'],
                    ],
                ],
                'PATCH api/mitra/service-bookings/{serviceBooking}/complete' => [
                    'notes' => 'Layanan homecare telah selesai.',
                    'summary' => 'Infus terpasang dengan baik, pasien stabil, keluarga sudah diberi edukasi perawatan.',
                ],
                'PATCH api/mitra/consultations/{consultation}/status' => [
                    'status' => 'ongoing',
                    'diagnosis' => 'Pasien perlu istirahat dan minum obat teratur.',
                    'notes' => 'Kontrol kembali bila gejala berlanjut.',
                ],
                'POST api/mitra/consultations/{consultation}/messages' => [
                    'message_type' => 'text',
                    'message' => 'Baik, silakan minum obat sesuai aturan.',
                    'attachment_path' => null,
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
                'POST api/admin/balance/users/{user}/refund' => [
                    'amount' => 50000,
                    'reference_type' => 'order',
                    'reference_id' => 1,
                    'description' => 'Refund pembayaran order.',
                ],
                'POST api/admin/balance/users/{user}/adjust' => [
                    'amount' => 25000,
                    'description' => 'Penyesuaian saldo oleh admin.',
                ],
                'POST api/admin/service-markup' => [
                    'service_id' => 1,
                    'markup_type' => 'percentage',
                    'markup_value' => 10,
                    'min_final_price' => 50000,
                    'priority' => 1,
                    'notes' => 'Markup layanan aktif.',
                ],
                'PATCH api/admin/service-markup/{serviceMarkupSetting}' => [
                    'markup_type' => 'fixed',
                    'markup_value' => 15000,
                    'min_final_price' => 50000,
                    'is_active' => true,
                    'priority' => 2,
                    'notes' => 'Update markup layanan.',
                ],
                'PATCH api/admin/service-markup/{serviceMarkupSetting}/toggle-status' => [
                    'is_active' => true,
                ],
                'POST api/admin/promo-codes' => [
                    'code' => 'SEHAT10',
                    'name' => 'Diskon Sehat 10%',
                    'description' => 'Promo layanan kesehatan.',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                    'min_purchase' => 50000,
                    'max_discount' => 25000,
                    'max_uses' => 100,
                    'max_uses_per_user' => 1,
                    'valid_from' => now()->format('Y-m-d'),
                    'valid_until' => now()->addMonth()->format('Y-m-d'),
                    'service_id' => 1,
                ],
                'PATCH api/admin/promo-codes/{promoCode}' => [
                    'code' => 'SEHAT15',
                    'name' => 'Diskon Sehat 15%',
                    'description' => 'Update promo layanan kesehatan.',
                    'discount_type' => 'percentage',
                    'discount_value' => 15,
                    'min_purchase' => 75000,
                    'max_discount' => 30000,
                    'max_uses' => 100,
                    'max_uses_per_user' => 1,
                    'valid_from' => now()->format('Y-m-d'),
                    'valid_until' => now()->addMonth()->format('Y-m-d'),
                    'service_id' => 1,
                    'is_active' => true,
                ],
                'PATCH api/admin/promo-codes/{promoCode}/toggle-status' => [
                    'is_active' => true,
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
