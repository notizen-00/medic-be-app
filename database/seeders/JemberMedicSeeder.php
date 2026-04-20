<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\CourierProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProfile;
use App\Models\PartnerSchedule;
use App\Models\PatientAddress;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JemberMedicSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $patientOne = User::updateOrCreate(
            ['email' => 'pasien.jember1@example.com'],
            [
                'name' => 'Ahmad Fauzi',
                'role' => 'pasien',
                'phone' => '081234567801',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $patientTwo = User::updateOrCreate(
            ['email' => 'pasien.jember2@example.com'],
            [
                'name' => 'Siti Rahma',
                'role' => 'pasien',
                'phone' => '081234567802',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $doctor = User::updateOrCreate(
            ['email' => 'dokter.jember@example.com'],
            [
                'name' => 'dr. Bima Pratama',
                'role' => 'dokter',
                'phone' => '081234567803',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $pharmacy = User::updateOrCreate(
            ['email' => 'apotik.jember@example.com'],
            [
                'name' => 'Apotik Sehat Jember',
                'role' => 'apotik',
                'phone' => '081234567804',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $courierOne = User::updateOrCreate(
            ['email' => 'kurir.jember1@example.com'],
            [
                'name' => 'Rizal Anwar',
                'role' => 'kurir',
                'phone' => '081234567805',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $courierTwo = User::updateOrCreate(
            ['email' => 'kurir.jember2@example.com'],
            [
                'name' => 'Deni Saputra',
                'role' => 'kurir',
                'phone' => '081234567806',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        PatientProfile::updateOrCreate(
            ['user_id' => $patientOne->id],
            [
                'date_of_birth' => '1995-04-12',
                'gender' => 'laki-laki',
                'address' => 'Jl. Gajah Mada No. 12, Kaliwates, Jember',
                'blood_type' => 'O',
                'emergency_contact_name' => 'Nur Hasanah',
                'emergency_contact_phone' => '081299991111',
                'allergies' => 'Alergi debu',
                'medical_notes' => 'Riwayat maag ringan',
            ]
        );

        PatientProfile::updateOrCreate(
            ['user_id' => $patientTwo->id],
            [
                'date_of_birth' => '1998-11-23',
                'gender' => 'perempuan',
                'address' => 'Jl. Mastrip No. 88, Sumbersari, Jember',
                'blood_type' => 'A',
                'emergency_contact_name' => 'Fajar Hidayat',
                'emergency_contact_phone' => '081299992222',
                'allergies' => 'Tidak ada',
                'medical_notes' => 'Sering migrain saat kelelahan',
            ]
        );

        PartnerProfile::updateOrCreate(
            ['user_id' => $doctor->id],
            [
                'profession' => 'dokter',
                'specialization' => 'Dokter Umum',
                'license_number' => 'SIP-DOK-JBR-001',
                'work_location' => 'Klinik Sehat Jember, Kaliwates, Jember',
                'years_of_experience' => 8,
                'consultation_fee' => 75000,
                'is_available' => true,
                'bio' => 'Dokter umum yang melayani konsultasi online dan kunjungan area Kota Jember.',
            ]
        );

        PartnerProfile::updateOrCreate(
            ['user_id' => $pharmacy->id],
            [
                'profession' => 'apotik',
                'pharmacy_name' => 'Apotik Sehat Jember',
                'specialization' => 'Apotek dan Penjualan Produk Kesehatan',
                'license_number' => 'SIA-JBR-001',
                'work_location' => 'Jl. Karimata No. 20, Sumbersari, Jember',
                'years_of_experience' => 10,
                'consultation_fee' => 0,
                'is_available' => true,
                'bio' => 'Apotik mitra di Kota Jember yang menyediakan obat resep dan produk kesehatan.',
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $courierOne->id],
            [
                'vehicle_type' => 'motor',
                'vehicle_number' => 'P 1234 AB',
                'license_number' => 'SIMC-JBR-001',
                'is_available' => true,
                'current_latitude' => -8.1724000,
                'current_longitude' => 113.7025000,
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $courierTwo->id],
            [
                'vehicle_type' => 'motor',
                'vehicle_number' => 'P 5678 CD',
                'license_number' => 'SIMC-JBR-002',
                'is_available' => true,
                'current_latitude' => -8.1689000,
                'current_longitude' => 113.7038000,
            ]
        );

        foreach ([1, 2, 3, 4, 5] as $day) {
            PartnerSchedule::updateOrCreate(
                [
                    'partner_user_id' => $doctor->id,
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '12:00:00',
                ],
                [
                    'slot_duration_minutes' => 30,
                    'is_active' => true,
                ]
            );

        }

        $products = [
            [
                'pharmacy_user_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-001',
                'name' => 'Paracetamol 500mg',
                'type' => 'obat',
                'category' => 'Demam dan Nyeri',
                'description' => 'Obat penurun demam dan pereda nyeri untuk kebutuhan harian.',
                'price' => 12000,
                'stock' => 150,
                'minimum_stock_alert' => 20,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_user_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-002',
                'name' => 'Amoxicillin 500mg',
                'type' => 'obat',
                'category' => 'Antibiotik',
                'description' => 'Obat antibiotik yang hanya ditebus menggunakan resep dokter.',
                'price' => 35000,
                'stock' => 80,
                'minimum_stock_alert' => 15,
                'track_stock' => true,
                'requires_prescription' => true,
                'is_active' => true,
            ],
            [
                'pharmacy_user_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-003',
                'name' => 'Vitamin C 1000mg',
                'type' => 'produk_kesehatan',
                'category' => 'Vitamin',
                'description' => 'Suplemen vitamin C untuk membantu menjaga daya tahan tubuh.',
                'price' => 28000,
                'stock' => 120,
                'minimum_stock_alert' => 10,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_user_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-004',
                'name' => 'Termometer Digital',
                'type' => 'produk_kesehatan',
                'category' => 'Alat Kesehatan',
                'description' => 'Termometer digital untuk kebutuhan rumah tangga.',
                'price' => 55000,
                'stock' => 40,
                'minimum_stock_alert' => 5,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_user_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-005',
                'name' => 'Salep Luka Antiseptik',
                'type' => 'obat',
                'category' => 'Perawatan Luka',
                'description' => 'Salep antiseptik untuk luka ringan dan lecet.',
                'price' => 22000,
                'stock' => 60,
                'minimum_stock_alert' => 10,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['sku' => $product['sku']], $product);
        }

        $addressOne = PatientAddress::updateOrCreate(
            ['patient_user_id' => $patientOne->id, 'label' => 'Rumah'],
            [
                'recipient_name' => 'Ahmad Fauzi',
                'recipient_phone' => '081234567801',
                'address' => 'Perumahan Kaliwates Indah Blok A2 No. 7, Kaliwates, Jember',
                'province' => 'Jawa Timur',
                'city' => 'Jember',
                'district' => 'Kaliwates',
                'postal_code' => '68131',
                'latitude' => -8.1732000,
                'longitude' => 113.6889000,
                'is_primary' => true,
            ]
        );

        $addressTwo = PatientAddress::updateOrCreate(
            ['patient_user_id' => $patientTwo->id, 'label' => 'Kos'],
            [
                'recipient_name' => 'Siti Rahma',
                'recipient_phone' => '081234567802',
                'address' => 'Jl. Jawa Gang 5 No. 14, Sumbersari, Jember',
                'province' => 'Jawa Timur',
                'city' => 'Jember',
                'district' => 'Sumbersari',
                'postal_code' => '68121',
                'latitude' => -8.1654000,
                'longitude' => 113.7162000,
                'is_primary' => true,
            ]
        );

        $consultation = Consultation::updateOrCreate(
            ['consultation_code' => 'KONS-JBR-0001'],
            [
                'patient_user_id' => $patientOne->id,
                'partner_user_id' => $doctor->id,
                'service_type' => 'chat',
                'status' => 'completed',
                'scheduled_at' => now()->subDays(1),
                'started_at' => now()->subDays(1)->addMinutes(5),
                'ended_at' => now()->subDays(1)->addMinutes(35),
                'complaint' => 'Demam dan sakit tenggorokan sejak dua hari.',
                'diagnosis' => 'Infeksi saluran napas atas ringan.',
                'notes' => 'Disarankan istirahat dan minum obat sesuai aturan.',
                'consultation_fee' => 75000,
            ]
        );

        ConsultationMessage::updateOrCreate(
            [
                'consultation_id' => $consultation->id,
                'sender_user_id' => $patientOne->id,
                'message' => 'Dok, saya demam sejak kemarin dan tenggorokan sakit.',
            ],
            [
                'message_type' => 'text',
                'read_at' => now()->subDays(1),
            ]
        );

        ConsultationMessage::updateOrCreate(
            [
                'consultation_id' => $consultation->id,
                'sender_user_id' => $doctor->id,
                'message' => 'Baik, saya sarankan minum obat dan cukup istirahat. Jika sesak segera ke fasilitas kesehatan terdekat.',
            ],
            [
                'message_type' => 'text',
                'read_at' => now()->subDays(1),
            ]
        );

        $prescription = Prescription::updateOrCreate(
            ['prescription_code' => 'RSP-JBR-0001'],
            [
                'consultation_id' => $consultation->id,
                'patient_user_id' => $patientOne->id,
                'partner_user_id' => $doctor->id,
                'status' => 'issued',
                'notes' => 'Tebus obat di area Kota Jember.',
            ]
        );

        $paracetamol = Product::where('sku', 'JBR-OBT-001')->firstOrFail();
        $amoxicillin = Product::where('sku', 'JBR-OBT-002')->firstOrFail();
        $vitaminC = Product::where('sku', 'JBR-OBT-003')->firstOrFail();

        PrescriptionItem::updateOrCreate(
            ['prescription_id' => $prescription->id, 'medicine_name' => 'Paracetamol 500mg'],
            [
                'dosage' => '500 mg',
                'frequency' => '3x sehari',
                'duration' => '3 hari',
                'quantity' => 10,
                'instructions' => 'Diminum setelah makan.',
            ]
        );

        PrescriptionItem::updateOrCreate(
            ['prescription_id' => $prescription->id, 'medicine_name' => 'Amoxicillin 500mg'],
            [
                'dosage' => '500 mg',
                'frequency' => '3x sehari',
                'duration' => '5 hari',
                'quantity' => 15,
                'instructions' => 'Habiskan sesuai anjuran dokter.',
            ]
        );

        Payment::updateOrCreate(
            ['payment_code' => 'PAY-KONS-JBR-0001'],
            [
                'consultation_id' => $consultation->id,
                'patient_user_id' => $patientOne->id,
                'payment_method' => 'bank_transfer',
                'status' => 'paid',
                'amount' => 75000,
                'paid_at' => now()->subDay(),
                'notes' => 'Pembayaran konsultasi online Jember.',
            ]
        );

        $orderOne = Order::updateOrCreate(
            ['order_code' => 'ORD-JBR-0001'],
            [
                'patient_user_id' => $patientOne->id,
                'pharmacy_user_id' => $pharmacy->id,
                'patient_address_id' => $addressOne->id,
                'prescription_id' => $prescription->id,
                'order_type' => 'resep',
                'status' => 'shipped',
                'subtotal' => 47000,
                'shipping_cost' => 10000,
                'total_amount' => 57000,
                'notes' => 'Pengantaran ke area Kaliwates, Jember.',
                'ordered_at' => now()->subHours(6),
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderOne->id, 'product_id' => $paracetamol->id],
            [
                'product_name' => $paracetamol->name,
                'unit_price' => $paracetamol->price,
                'quantity' => 1,
                'total_price' => $paracetamol->price,
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderOne->id, 'product_id' => $amoxicillin->id],
            [
                'product_name' => $amoxicillin->name,
                'unit_price' => $amoxicillin->price,
                'quantity' => 1,
                'total_price' => $amoxicillin->price,
            ]
        );

        $orderTwo = Order::updateOrCreate(
            ['order_code' => 'ORD-JBR-0002'],
            [
                'patient_user_id' => $patientTwo->id,
                'pharmacy_user_id' => $pharmacy->id,
                'patient_address_id' => $addressTwo->id,
                'prescription_id' => null,
                'order_type' => 'non_resep',
                'status' => 'delivered',
                'subtotal' => 83000,
                'shipping_cost' => 12000,
                'total_amount' => 95000,
                'notes' => 'Pesanan vitamin dan alat kesehatan area Sumbersari, Jember.',
                'ordered_at' => now()->subDay(),
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderTwo->id, 'product_id' => $vitaminC->id],
            [
                'product_name' => $vitaminC->name,
                'unit_price' => $vitaminC->price,
                'quantity' => 1,
                'total_price' => $vitaminC->price,
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderTwo->id, 'product_id' => Product::where('sku', 'JBR-OBT-004')->firstOrFail()->id],
            [
                'product_name' => 'Termometer Digital',
                'unit_price' => 55000,
                'quantity' => 1,
                'total_price' => 55000,
            ]
        );

        $shipmentOne = Shipment::updateOrCreate(
            ['shipment_code' => 'SHP-JBR-0001'],
            [
                'order_id' => $orderOne->id,
                'courier_user_id' => $courierOne->id,
                'delivery_type' => 'same_day',
                'status' => 'on_delivery',
                'assigned_at' => now()->subHours(5),
                'picked_up_at' => now()->subHours(4),
                'delivered_at' => null,
                'notes' => 'Kurir sedang menuju alamat pasien di Kaliwates, Jember.',
            ]
        );

        $shipmentTwo = Shipment::updateOrCreate(
            ['shipment_code' => 'SHP-JBR-0002'],
            [
                'order_id' => $orderTwo->id,
                'courier_user_id' => $courierTwo->id,
                'delivery_type' => 'same_day',
                'status' => 'delivered',
                'assigned_at' => now()->subDay()->addHour(),
                'picked_up_at' => now()->subDay()->addHours(2),
                'delivered_at' => now()->subHours(20),
                'notes' => 'Pesanan berhasil diterima di Sumbersari, Jember.',
            ]
        );

        $shipmentHistories = [
            [
                'shipment_id' => $shipmentOne->id,
                'status' => 'waiting_courier',
                'title' => 'Menunggu kurir',
                'description' => 'Pesanan sedang menunggu penugasan kurir area Jember.',
                'logged_at' => now()->subHours(6),
            ],
            [
                'shipment_id' => $shipmentOne->id,
                'status' => 'picked_up',
                'title' => 'Pesanan diambil',
                'description' => 'Kurir telah mengambil pesanan dari apotek mitra Jember.',
                'logged_at' => now()->subHours(4),
            ],
            [
                'shipment_id' => $shipmentOne->id,
                'status' => 'on_delivery',
                'title' => 'Dalam pengantaran',
                'description' => 'Kurir sedang menuju alamat pasien di Kaliwates.',
                'logged_at' => now()->subHours(2),
            ],
            [
                'shipment_id' => $shipmentTwo->id,
                'status' => 'waiting_courier',
                'title' => 'Menunggu kurir',
                'description' => 'Order disiapkan untuk pengantaran di Sumbersari.',
                'logged_at' => now()->subDay(),
            ],
            [
                'shipment_id' => $shipmentTwo->id,
                'status' => 'picked_up',
                'title' => 'Pesanan diambil',
                'description' => 'Kurir telah mengambil pesanan dari gudang Jember.',
                'logged_at' => now()->subDay()->addHours(2),
            ],
            [
                'shipment_id' => $shipmentTwo->id,
                'status' => 'delivered',
                'title' => 'Pesanan diterima',
                'description' => 'Pesanan diterima pasien di wilayah Sumbersari, Jember.',
                'logged_at' => now()->subHours(20),
            ],
        ];

        foreach ($shipmentHistories as $history) {
            ShipmentHistory::updateOrCreate(
                [
                    'shipment_id' => $history['shipment_id'],
                    'status' => $history['status'],
                    'title' => $history['title'],
                ],
                [
                    'description' => $history['description'],
                    'logged_at' => $history['logged_at'],
                ]
            );
        }

        Payment::updateOrCreate(
            ['payment_code' => 'PAY-ORD-JBR-0001'],
            [
                'consultation_id' => null,
                'patient_user_id' => $patientOne->id,
                'payment_method' => 'wallet',
                'status' => 'paid',
                'amount' => 57000,
                'paid_at' => now()->subHours(6),
                'notes' => 'Pembayaran order resep area Jember.',
            ]
        );

        Payment::updateOrCreate(
            ['payment_code' => 'PAY-ORD-JBR-0002'],
            [
                'consultation_id' => null,
                'patient_user_id' => $patientTwo->id,
                'payment_method' => 'bank_transfer',
                'status' => 'paid',
                'amount' => 95000,
                'paid_at' => now()->subDay(),
                'notes' => 'Pembayaran produk kesehatan area Jember.',
            ]
        );
    }
}
