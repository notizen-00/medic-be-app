<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\ConsultationMessage;
use App\Models\CourierProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProfile;
use App\Models\PartnerService;
use App\Models\PartnerSchedule;
use App\Models\PatientAddress;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\PharmacyProfile;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceCategory;
use App\Models\Shipment;
use App\Models\ShipmentHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        User::updateOrCreate(
            ['email' => 'admin.jember@example.com'],
            [
                'name' => 'Admin Medic Jember',
                'role' => 'admin',
                'phone' => '081234567800',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $doctor = User::updateOrCreate(
            ['email' => 'dokter.jember@example.com'],
            [
                'name' => 'dr. Bima Pratama',
                'role' => 'mitra',
                'phone' => '081234567803',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $nurse = User::updateOrCreate(
            ['email' => 'perawat.jember@example.com'],
            [
                'name' => 'Ns. Laras Widya',
                'role' => 'mitra',
                'phone' => '081234567808',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $pharmacyOwner = User::updateOrCreate(
            ['email' => 'apotik.jember@example.com'],
            [
                'name' => 'Apotik Sehat Jember',
                'role' => 'mitra',
                'phone' => '081234567804',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $pharmacyOwnerTwo = User::updateOrCreate(
            ['email' => 'apotik.kota@example.com'],
            [
                'name' => 'Apotik Kota Jember',
                'role' => 'mitra',
                'phone' => '081234567807',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $courierOne = User::updateOrCreate(
            ['email' => 'kurir.jember1@example.com'],
            [
                'name' => 'Rizal Anwar',
                'role' => 'mitra',
                'phone' => '081234567805',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $courierTwo = User::updateOrCreate(
            ['email' => 'kurir.jember2@example.com'],
            [
                'name' => 'Deni Saputra',
                'role' => 'mitra',
                'phone' => '081234567806',
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        $additionalDoctors = [
            [
                'name' => 'dr. Andi Saputra',
                'email' => 'dokter.jember01@example.com',
                'phone' => '081234567820',
                'specialization' => 'Spesialis Penyakit Dalam',
                'license_number' => 'SIP-DOK-JBR-002',
                'work_location' => 'Klinik Cempaka Medika, Kaliwates, Jember',
                'latitude' => -8.1741000,
                'longitude' => 113.6952000,
                'years_of_experience' => 9,
                'consultation_fee' => 120000,
                'bio' => 'Dokter penyakit dalam untuk konsultasi keluhan metabolik, pencernaan, dan penyakit kronis.',
            ],
            [
                'name' => 'dr. Bella Maharani, Sp.A',
                'email' => 'dokter.jember02@example.com',
                'phone' => '081234567821',
                'specialization' => 'Spesialis Anak',
                'license_number' => 'SIP-DOK-JBR-003',
                'work_location' => 'Klinik Tumbuh Sehat, Sumbersari, Jember',
                'latitude' => -8.1667000,
                'longitude' => 113.7190000,
                'years_of_experience' => 8,
                'consultation_fee' => 130000,
                'bio' => 'Fokus pada tumbuh kembang anak, demam, dan edukasi orang tua.',
            ],
            [
                'name' => 'dr. Citra Lestari, Sp.OG',
                'email' => 'dokter.jember03@example.com',
                'phone' => '081234567822',
                'specialization' => 'Spesialis Kandungan',
                'license_number' => 'SIP-DOK-JBR-004',
                'work_location' => 'RSIA Bunda Jember, Patrang, Jember',
                'latitude' => -8.1519000,
                'longitude' => 113.7044000,
                'years_of_experience' => 11,
                'consultation_fee' => 150000,
                'bio' => 'Melayani konsultasi kehamilan, gangguan haid, dan kesehatan reproduksi wanita.',
            ],
            [
                'name' => 'dr. Dimas Prakoso, Sp.JP',
                'email' => 'dokter.jember04@example.com',
                'phone' => '081234567823',
                'specialization' => 'Spesialis Jantung',
                'license_number' => 'SIP-DOK-JBR-005',
                'work_location' => 'Heart Care Clinic, Kaliwates, Jember',
                'latitude' => -8.1762000,
                'longitude' => 113.6907000,
                'years_of_experience' => 12,
                'consultation_fee' => 165000,
                'bio' => 'Konsultasi jantung, hipertensi, dan evaluasi risiko penyakit kardiovaskular.',
            ],
            [
                'name' => 'dr. Eka Purnama, Sp.KK',
                'email' => 'dokter.jember05@example.com',
                'phone' => '081234567824',
                'specialization' => 'Spesialis Kulit dan Kelamin',
                'license_number' => 'SIP-DOK-JBR-006',
                'work_location' => 'Dermacare Jember, Sumbersari, Jember',
                'latitude' => -8.1635000,
                'longitude' => 113.7234000,
                'years_of_experience' => 7,
                'consultation_fee' => 140000,
                'bio' => 'Menangani masalah kulit, alergi, jerawat, dan infeksi kulit.',
            ],
            [
                'name' => 'dr. Fajar Hidayat, Sp.THT',
                'email' => 'dokter.jember06@example.com',
                'phone' => '081234567825',
                'specialization' => 'Spesialis THT',
                'license_number' => 'SIP-DOK-JBR-007',
                'work_location' => 'Klinik THT Sejahtera, Patrang, Jember',
                'latitude' => -8.1482000,
                'longitude' => 113.7078000,
                'years_of_experience' => 10,
                'consultation_fee' => 145000,
                'bio' => 'Fokus pada gangguan telinga, hidung, tenggorokan, dan sinus.',
            ],
            [
                'name' => 'dr. Gina Aprilia, Sp.M',
                'email' => 'dokter.jember07@example.com',
                'phone' => '081234567826',
                'specialization' => 'Spesialis Mata',
                'license_number' => 'SIP-DOK-JBR-008',
                'work_location' => 'Jember Eye Center, Kaliwates, Jember',
                'latitude' => -8.1714000,
                'longitude' => 113.6926000,
                'years_of_experience' => 9,
                'consultation_fee' => 150000,
                'bio' => 'Konsultasi gangguan penglihatan, iritasi mata, dan kontrol kesehatan mata rutin.',
            ],
            [
                'name' => 'dr. Hendra Kurniawan, Sp.OT',
                'email' => 'dokter.jember08@example.com',
                'phone' => '081234567827',
                'specialization' => 'Spesialis Ortopedi',
                'license_number' => 'SIP-DOK-JBR-009',
                'work_location' => 'Ortho Care Jember, Ajung, Jember',
                'latitude' => -8.2087000,
                'longitude' => 113.7195000,
                'years_of_experience' => 13,
                'consultation_fee' => 170000,
                'bio' => 'Menangani cedera tulang, sendi, nyeri punggung, dan rehabilitasi muskuloskeletal.',
            ],
            [
                'name' => 'dr. Intan Permata, Sp.S',
                'email' => 'dokter.jember09@example.com',
                'phone' => '081234567828',
                'specialization' => 'Spesialis Saraf',
                'license_number' => 'SIP-DOK-JBR-010',
                'work_location' => 'Neuro Clinic Jember, Kaliwates, Jember',
                'latitude' => -8.1771000,
                'longitude' => 113.6973000,
                'years_of_experience' => 10,
                'consultation_fee' => 160000,
                'bio' => 'Konsultasi migrain, vertigo, neuropati, dan gangguan saraf perifer.',
            ],
            [
                'name' => 'dr. Joko Wibowo, Sp.B',
                'email' => 'dokter.jember10@example.com',
                'phone' => '081234567829',
                'specialization' => 'Spesialis Bedah Umum',
                'license_number' => 'SIP-DOK-JBR-011',
                'work_location' => 'Klinik Bedah Prima, Patrang, Jember',
                'latitude' => -8.1568000,
                'longitude' => 113.7102000,
                'years_of_experience' => 14,
                'consultation_fee' => 175000,
                'bio' => 'Melayani evaluasi pra operasi, kontrol pasca operasi, dan tindakan bedah umum.',
            ],
            [
                'name' => 'dr. Kartika Sari, Sp.P',
                'email' => 'dokter.jember11@example.com',
                'phone' => '081234567830',
                'specialization' => 'Spesialis Paru',
                'license_number' => 'SIP-DOK-JBR-012',
                'work_location' => 'Pulmo Care Jember, Sumbersari, Jember',
                'latitude' => -8.1627000,
                'longitude' => 113.7186000,
                'years_of_experience' => 8,
                'consultation_fee' => 150000,
                'bio' => 'Fokus pada asma, infeksi saluran napas, dan penyakit paru kronis.',
            ],
            [
                'name' => 'dr. Luthfi Ramadhan, Sp.KJ',
                'email' => 'dokter.jember12@example.com',
                'phone' => '081234567831',
                'specialization' => 'Spesialis Kesehatan Jiwa',
                'license_number' => 'SIP-DOK-JBR-013',
                'work_location' => 'Mental Wellness Clinic, Kaliwates, Jember',
                'latitude' => -8.1708000,
                'longitude' => 113.7009000,
                'years_of_experience' => 9,
                'consultation_fee' => 155000,
                'bio' => 'Pendampingan gangguan kecemasan, stres, dan kesehatan mental dewasa.',
            ],
            [
                'name' => 'dr. Maya Oktaviani, Sp.GK',
                'email' => 'dokter.jember13@example.com',
                'phone' => '081234567832',
                'specialization' => 'Spesialis Gizi Klinik',
                'license_number' => 'SIP-DOK-JBR-014',
                'work_location' => 'Nutri Health Center, Kaliwates, Jember',
                'latitude' => -8.1729000,
                'longitude' => 113.6941000,
                'years_of_experience' => 6,
                'consultation_fee' => 135000,
                'bio' => 'Konsultasi pola makan untuk obesitas, diabetes, dan pemulihan pasca sakit.',
            ],
            [
                'name' => 'dr. Nanda Putri, Sp.RM',
                'email' => 'dokter.jember14@example.com',
                'phone' => '081234567833',
                'specialization' => 'Spesialis Rehabilitasi Medik',
                'license_number' => 'SIP-DOK-JBR-015',
                'work_location' => 'Rehab Point Jember, Arjasa, Jember',
                'latitude' => -8.1217000,
                'longitude' => 113.7271000,
                'years_of_experience' => 7,
                'consultation_fee' => 145000,
                'bio' => 'Menangani pemulihan fungsi gerak pasca cedera dan terapi fisik ringan.',
            ],
            [
                'name' => 'dr. Oki Firmansyah, Sp.U',
                'email' => 'dokter.jember15@example.com',
                'phone' => '081234567834',
                'specialization' => 'Spesialis Urologi',
                'license_number' => 'SIP-DOK-JBR-016',
                'work_location' => 'Uro Medika Jember, Pakusari, Jember',
                'latitude' => -8.1379000,
                'longitude' => 113.7391000,
                'years_of_experience' => 11,
                'consultation_fee' => 160000,
                'bio' => 'Konsultasi batu saluran kemih, gangguan berkemih, dan kesehatan prostat.',
            ],
            [
                'name' => 'dr. Putri Nabila, Sp.PD-KEMD',
                'email' => 'dokter.jember16@example.com',
                'phone' => '081234567835',
                'specialization' => 'Spesialis Endokrin Metabolik',
                'license_number' => 'SIP-DOK-JBR-017',
                'work_location' => 'Metabolic Care Jember, Sumbersari, Jember',
                'latitude' => -8.1659000,
                'longitude' => 113.7208000,
                'years_of_experience' => 10,
                'consultation_fee' => 165000,
                'bio' => 'Menangani diabetes, gangguan tiroid, dan masalah hormonal metabolik.',
            ],
            [
                'name' => 'dr. Qori Aulia, Sp.Onk',
                'email' => 'dokter.jember17@example.com',
                'phone' => '081234567836',
                'specialization' => 'Spesialis Onkologi',
                'license_number' => 'SIP-DOK-JBR-018',
                'work_location' => 'Cancer Support Clinic, Kaliwates, Jember',
                'latitude' => -8.1755000,
                'longitude' => 113.6899000,
                'years_of_experience' => 12,
                'consultation_fee' => 180000,
                'bio' => 'Pendampingan pasien kanker untuk kontrol rutin, edukasi terapi, dan manajemen gejala.',
            ],
            [
                'name' => 'dr. Raka Aditya, Sp.Rad',
                'email' => 'dokter.jember18@example.com',
                'phone' => '081234567837',
                'specialization' => 'Spesialis Radiologi',
                'license_number' => 'SIP-DOK-JBR-019',
                'work_location' => 'Radiologi Medika Jember, Patrang, Jember',
                'latitude' => -8.1542000,
                'longitude' => 113.7085000,
                'years_of_experience' => 9,
                'consultation_fee' => 150000,
                'bio' => 'Melayani interpretasi penunjang radiologi dan konsultasi hasil pencitraan medis.',
            ],
            [
                'name' => 'dr. Shinta Ayu, Sp.KFR',
                'email' => 'dokter.jember19@example.com',
                'phone' => '081234567838',
                'specialization' => 'Spesialis Kedokteran Fisik dan Rehabilitasi',
                'license_number' => 'SIP-DOK-JBR-020',
                'work_location' => 'Physio Med Jember, Rambipuji, Jember',
                'latitude' => -8.2239000,
                'longitude' => 113.6112000,
                'years_of_experience' => 8,
                'consultation_fee' => 150000,
                'bio' => 'Fokus pada rehabilitasi fisik, keluhan muskuloskeletal, dan pemulihan mobilitas.',
            ],
        ];

        $seededAdditionalDoctorUsers = collect($additionalDoctors)->map(function (array $doctorData) use ($password) {
            $user = User::updateOrCreate(
                ['email' => $doctorData['email']],
                [
                    'name' => $doctorData['name'],
                    'role' => 'mitra',
                    'phone' => $doctorData['phone'],
                    'email_verified_at' => now(),
                    'password' => $password,
                ]
            );

            $documentPaths = $this->seedPartnerDocuments($user->id, $user->name, $doctorData['license_number']);

            PartnerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profession' => 'dokter',
                    'specialization' => $doctorData['specialization'],
                    'license_number' => $doctorData['license_number'],
                    'work_location' => $doctorData['work_location'],
                    'latitude' => $doctorData['latitude'],
                    'longitude' => $doctorData['longitude'],
                    'years_of_experience' => $doctorData['years_of_experience'],
                    'consultation_fee' => $doctorData['consultation_fee'],
                    'is_available' => true,
                    'bio' => $doctorData['bio'],
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'str_photo_path' => $documentPaths['str_photo_path'],
                    'ktp_photo_path' => $documentPaths['ktp_photo_path'],
                ]
            );

            return [
                'user' => $user,
                'consultation_fee' => $doctorData['consultation_fee'],
            ];
        })->values();

        $additionalNurses = [
            [
                'name' => 'Ns. Dita Puspita',
                'email' => 'perawat.jember01@example.com',
                'phone' => '081234567810',
                'specialization' => 'Perawat Homecare Dewasa',
                'license_number' => 'SIPP-JBR-010',
                'work_location' => 'Area Patrang, Jember',
                'latitude' => -8.1524000,
                'longitude' => 113.7031000,
                'years_of_experience' => 4,
                'consultation_fee' => 55000,
                'bio' => 'Perawat homecare untuk perawatan pasien dewasa area Patrang.',
            ],
            [
                'name' => 'Ns. Rina Maharani',
                'email' => 'perawat.jember02@example.com',
                'phone' => '081234567811',
                'specialization' => 'Perawat Rawat Luka',
                'license_number' => 'SIPP-JBR-011',
                'work_location' => 'Area Kaliwates, Jember',
                'latitude' => -8.1786000,
                'longitude' => 113.6915000,
                'years_of_experience' => 7,
                'consultation_fee' => 60000,
                'bio' => 'Fokus pada layanan rawat luka dan observasi kondisi pasien di rumah.',
            ],
            [
                'name' => 'Ns. Fajar Lestari',
                'email' => 'perawat.jember03@example.com',
                'phone' => '081234567812',
                'specialization' => 'Perawat Geriatri',
                'license_number' => 'SIPP-JBR-012',
                'work_location' => 'Area Sumbersari, Jember',
                'latitude' => -8.1649000,
                'longitude' => 113.7213000,
                'years_of_experience' => 5,
                'consultation_fee' => 58000,
                'bio' => 'Pendampingan pasien lansia dengan kunjungan rutin area Sumbersari.',
            ],
            [
                'name' => 'Ns. Maya Kirana',
                'email' => 'perawat.jember04@example.com',
                'phone' => '081234567813',
                'specialization' => 'Perawat Infus dan Injeksi',
                'license_number' => 'SIPP-JBR-013',
                'work_location' => 'Area Ajung, Jember',
                'latitude' => -8.2108000,
                'longitude' => 113.7216000,
                'years_of_experience' => 6,
                'consultation_fee' => 62000,
                'bio' => 'Melayani pemasangan infus, injeksi, dan observasi pasien di rumah.',
            ],
            [
                'name' => 'Ns. Tegar Ramadhan',
                'email' => 'perawat.jember05@example.com',
                'phone' => '081234567814',
                'specialization' => 'Perawat Post Operasi',
                'license_number' => 'SIPP-JBR-014',
                'work_location' => 'Area Pakusari, Jember',
                'latitude' => -8.1357000,
                'longitude' => 113.7419000,
                'years_of_experience' => 8,
                'consultation_fee' => 65000,
                'bio' => 'Pendampingan pasien pasca operasi dan rawat luka lanjutan area Pakusari.',
            ],
            [
                'name' => 'Ns. Vina Oktavia',
                'email' => 'perawat.jember06@example.com',
                'phone' => '081234567815',
                'specialization' => 'Perawat Homecare Anak',
                'license_number' => 'SIPP-JBR-015',
                'work_location' => 'Area Arjasa, Jember',
                'latitude' => -8.1198000,
                'longitude' => 113.7264000,
                'years_of_experience' => 5,
                'consultation_fee' => 57000,
                'bio' => 'Homecare anak untuk tindakan dasar dan monitoring kondisi pasien.',
            ],
            [
                'name' => 'Ns. Yoga Pranata',
                'email' => 'perawat.jember07@example.com',
                'phone' => '081234567816',
                'specialization' => 'Perawat Homecare Umum',
                'license_number' => 'SIPP-JBR-016',
                'work_location' => 'Area Rambipuji, Jember',
                'latitude' => -8.2223000,
                'longitude' => 113.6089000,
                'years_of_experience' => 4,
                'consultation_fee' => 54000,
                'bio' => 'Layanan homecare umum untuk wilayah Rambipuji dan sekitarnya.',
            ],
            [
                'name' => 'Ns. Siska Amelia',
                'email' => 'perawat.jember08@example.com',
                'phone' => '081234567817',
                'specialization' => 'Perawat Rawat Luka Diabetes',
                'license_number' => 'SIPP-JBR-017',
                'work_location' => 'Area Balung, Jember',
                'latitude' => -8.2741000,
                'longitude' => 113.5448000,
                'years_of_experience' => 9,
                'consultation_fee' => 68000,
                'bio' => 'Fokus pada perawatan luka diabetes dan edukasi keluarga pasien.',
            ],
            [
                'name' => 'Ns. Reza Anindita',
                'email' => 'perawat.jember09@example.com',
                'phone' => '081234567818',
                'specialization' => 'Perawat Rehabilitasi Ringan',
                'license_number' => 'SIPP-JBR-018',
                'work_location' => 'Area Ambulu, Jember',
                'latitude' => -8.3456000,
                'longitude' => 113.6051000,
                'years_of_experience' => 6,
                'consultation_fee' => 61000,
                'bio' => 'Pendampingan rehabilitasi ringan dan monitoring mobilitas pasien di rumah.',
            ],
            [
                'name' => 'Ns. Nabila Safitri',
                'email' => 'perawat.jember10@example.com',
                'phone' => '081234567819',
                'specialization' => 'Perawat Homecare Malam',
                'license_number' => 'SIPP-JBR-019',
                'work_location' => 'Area Wuluhan, Jember',
                'latitude' => -8.2985000,
                'longitude' => 113.6824000,
                'years_of_experience' => 7,
                'consultation_fee' => 63000,
                'bio' => 'Layanan perawat homecare untuk observasi malam dan pendampingan pasien.',
            ],
        ];

        $seededAdditionalNurseUsers = collect($additionalNurses)->map(function (array $nurseData) use ($password) {
            $user = User::updateOrCreate(
                ['email' => $nurseData['email']],
                [
                    'name' => $nurseData['name'],
                    'role' => 'mitra',
                    'phone' => $nurseData['phone'],
                    'email_verified_at' => now(),
                    'password' => $password,
                ]
            );

            $documentPaths = $this->seedPartnerDocuments($user->id, $user->name, $nurseData['license_number']);

            PartnerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profession' => 'perawat',
                    'specialization' => $nurseData['specialization'],
                    'license_number' => $nurseData['license_number'],
                    'work_location' => $nurseData['work_location'],
                    'latitude' => $nurseData['latitude'],
                    'longitude' => $nurseData['longitude'],
                    'years_of_experience' => $nurseData['years_of_experience'],
                    'consultation_fee' => $nurseData['consultation_fee'],
                    'is_available' => true,
                    'bio' => $nurseData['bio'],
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'str_photo_path' => $documentPaths['str_photo_path'],
                    'ktp_photo_path' => $documentPaths['ktp_photo_path'],
                ]
            );

            return $user;
        })->values();

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
                'latitude' => -8.1721000,
                'longitude' => 113.6998000,
                'years_of_experience' => 8,
                'consultation_fee' => 75000,
                'is_available' => true,
                'bio' => 'Dokter umum yang melayani konsultasi online dan kunjungan area Kota Jember.',
                'verification_status' => 'verified',
                'verified_at' => now(),
                ...$this->seedPartnerDocuments($doctor->id, $doctor->name, 'SIP-DOK-JBR-001'),
            ]
        );

        PartnerProfile::updateOrCreate(
            ['user_id' => $nurse->id],
            [
                'profession' => 'perawat',
                'specialization' => 'Perawat Homecare',
                'license_number' => 'SIPP-JBR-003',
                'work_location' => 'Area Sumbersari dan Kaliwates, Jember',
                'latitude' => -8.1703000,
                'longitude' => 113.7052000,
                'years_of_experience' => 6,
                'consultation_fee' => 50000,
                'is_available' => true,
                'bio' => 'Perawat homecare untuk rawat luka, pemasangan infus, dan pendampingan pasien di rumah.',
                'verification_status' => 'verified',
                'verified_at' => now(),
                ...$this->seedPartnerDocuments($nurse->id, $nurse->name, 'SIPP-JBR-003'),
            ]
        );

        $pharmacyLegacyColumns = [];

        if (Schema::hasColumn('pharmacies', 'name')) {
            $pharmacyLegacyColumns = [
                'name' => 'Apotik Sehat Jember',
                'license_number' => 'SIA-JBR-001',
                'address' => 'Jl. Karimata No. 20, Sumbersari, Jember',
                'latitude' => -8.1662000,
                'longitude' => 113.7171000,
                'description' => 'Apotik mitra di Kota Jember yang menyediakan obat resep dan produk kesehatan.',
            ];
        }

        $pharmacy = Pharmacy::unguarded(fn () => Pharmacy::updateOrCreate(
            ['owner_user_id' => $pharmacyOwner->id],
            [
                'is_active' => true,
                ...$pharmacyLegacyColumns,
            ]
        ));

        $pharmacyTwoLegacyColumns = [];

        if (Schema::hasColumn('pharmacies', 'name')) {
            $pharmacyTwoLegacyColumns = [
                'name' => 'Apotik Kota Jember',
                'license_number' => 'SIA-JBR-002',
                'address' => 'Jl. Sultan Agung No. 45, Kaliwates, Jember',
                'latitude' => -8.1738000,
                'longitude' => 113.6881000,
                'description' => 'Apotik pusat kota dengan layanan pengiriman cepat area Kaliwates dan Patrang.',
            ];
        }

        $pharmacyTwo = Pharmacy::unguarded(fn () => Pharmacy::updateOrCreate(
            ['owner_user_id' => $pharmacyOwnerTwo->id],
            [
                'is_active' => true,
                ...$pharmacyTwoLegacyColumns,
            ]
        ));

        PharmacyProfile::updateOrCreate(
            ['pharmacy_id' => $pharmacy->id],
            [
                'name' => 'Apotik Sehat Jember',
                'license_number' => 'SIA-JBR-001',
                'address' => 'Jl. Karimata No. 20, Sumbersari, Jember',
                'latitude' => -8.1662000,
                'longitude' => 113.7171000,
                'opening_time' => '08:00:00',
                'closing_time' => '22:00:00',
                'description' => 'Apotik mitra di Kota Jember yang menyediakan obat resep dan produk kesehatan.',
            ]
        );

        PharmacyProfile::updateOrCreate(
            ['pharmacy_id' => $pharmacyTwo->id],
            [
                'name' => 'Apotik Kota Jember',
                'license_number' => 'SIA-JBR-002',
                'address' => 'Jl. Sultan Agung No. 45, Kaliwates, Jember',
                'latitude' => -8.1738000,
                'longitude' => 113.6881000,
                'opening_time' => '07:30:00',
                'closing_time' => '21:30:00',
                'description' => 'Apotik pusat kota dengan layanan pengiriman cepat area Kaliwates dan Patrang.',
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

        foreach ($seededAdditionalDoctorUsers as $index => $additionalDoctor) {
            foreach ([1, 2, 3, 4, 5] as $day) {
                PartnerSchedule::updateOrCreate(
                    [
                        'partner_user_id' => $additionalDoctor['user']->id,
                        'day_of_week' => $day,
                        'start_time' => $index % 2 === 0 ? '09:00:00' : '13:00:00',
                        'end_time' => $index % 2 === 0 ? '13:00:00' : '17:00:00',
                    ],
                    [
                        'slot_duration_minutes' => 30,
                        'is_active' => true,
                    ]
                );
            }
        }

        $products = [
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-001',
                'name' => 'Paracetamol 500mg',
                'type' => 'obat',
                'category' => 'Demam dan Nyeri',
                'description' => 'Obat penurun demam dan pereda nyeri untuk kebutuhan harian.',
                'price' => 12000,
                'cost_price' => 8000,
                'stock' => 150,
                'minimum_stock_alert' => 20,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacyTwo->id,
                'sku' => 'JBR-OBT-001',
                'name' => 'Paracetamol 500mg',
                'type' => 'obat',
                'category' => 'Demam dan Nyeri',
                'description' => 'Obat penurun demam dan pereda nyeri dari apotik pusat kota.',
                'price' => 12500,
                'cost_price' => 8500,
                'stock' => 200,
                'minimum_stock_alert' => 25,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-002',
                'name' => 'Amoxicillin 500mg',
                'type' => 'obat',
                'category' => 'Antibiotik',
                'description' => 'Obat antibiotik yang hanya ditebus menggunakan resep dokter.',
                'price' => 35000,
                'cost_price' => 25000,
                'stock' => 80,
                'minimum_stock_alert' => 15,
                'track_stock' => true,
                'requires_prescription' => true,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacyTwo->id,
                'sku' => 'JBR-OBT-002',
                'name' => 'Amoxicillin 500mg',
                'type' => 'obat',
                'category' => 'Antibiotik',
                'description' => 'Obat antibiotik resep dari apotik pusat kota.',
                'price' => 36000,
                'cost_price' => 26000,
                'stock' => 50,
                'minimum_stock_alert' => 10,
                'track_stock' => true,
                'requires_prescription' => true,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-003',
                'name' => 'Vitamin C 1000mg',
                'type' => 'produk_kesehatan',
                'category' => 'Vitamin',
                'description' => 'Suplemen vitamin C untuk membantu menjaga daya tahan tubuh.',
                'price' => 28000,
                'cost_price' => 18000,
                'stock' => 120,
                'minimum_stock_alert' => 10,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacyTwo->id,
                'sku' => 'JBR-OBT-003',
                'name' => 'Vitamin C 1000mg',
                'type' => 'produk_kesehatan',
                'category' => 'Vitamin',
                'description' => 'Suplemen vitamin C dari apotik pusat kota.',
                'price' => 29000,
                'cost_price' => 19000,
                'stock' => 140,
                'minimum_stock_alert' => 15,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-004',
                'name' => 'Termometer Digital',
                'type' => 'produk_kesehatan',
                'category' => 'Alat Kesehatan',
                'description' => 'Termometer digital untuk kebutuhan rumah tangga.',
                'price' => 55000,
                'cost_price' => 40000,
                'stock' => 40,
                'minimum_stock_alert' => 5,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-OBT-005',
                'name' => 'Salep Luka Antiseptik',
                'type' => 'obat',
                'category' => 'Perawatan Luka',
                'description' => 'Salep antiseptik untuk luka ringan dan lecet.',
                'price' => 22000,
                'cost_price' => 15000,
                'stock' => 60,
                'minimum_stock_alert' => 10,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-RNT-001',
                'name' => 'Sewa Ambulans',
                'type' => 'sewa_alat_kesehatan',
                'category' => 'Transportasi Medis',
                'description' => 'Layanan sewa ambulans untuk antar jemput pasien non-gawat dan rujukan terjadwal area Jember.',
                'price' => 550000,
                'stock' => 2,
                'minimum_stock_alert' => 0,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
            [
                'pharmacy_id' => $pharmacy->id,
                'sku' => 'JBR-RNT-002',
                'name' => 'Sewa Ventilator',
                'type' => 'sewa_alat_kesehatan',
                'category' => 'Sewa Alat Kesehatan',
                'description' => 'Sewa ventilator rumahan lengkap dengan edukasi penggunaan awal dan koordinasi instalasi.',
                'price' => 1750000,
                'stock' => 3,
                'minimum_stock_alert' => 1,
                'track_stock' => true,
                'requires_prescription' => false,
                'is_active' => true,
            ],
        ];

        $productPharmacyOwners = [
            $pharmacy->id => $pharmacyOwner->id,
            $pharmacyTwo->id => $pharmacyOwnerTwo->id,
        ];

        foreach ($products as $product) {
            if (Schema::hasColumn('products', 'pharmacy_user_id')) {
                $product['pharmacy_user_id'] = $productPharmacyOwners[$product['pharmacy_id']];
            }

            Product::unguarded(fn () => Product::updateOrCreate(
                [
                    'pharmacy_id' => $product['pharmacy_id'],
                    'sku' => $product['sku'],
                ],
                $product
            ));
        }

        $serviceCategories = collect([
            [
                'name' => 'Doctor',
                'slug' => 'doctor',
                'icon' => 'stethoscope',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Nurse',
                'slug' => 'nurse',
                'icon' => 'heart-pulse',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Midwife',
                'slug' => 'midwife',
                'icon' => 'baby',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Caregiver',
                'slug' => 'caregiver',
                'icon' => 'hand-heart',
                'sort_order' => 40,
                'is_active' => true,
            ],
        ])->mapWithKeys(function (array $category) {
            $model = ServiceCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );

            return [$category['slug'] => $model];
        });

        $services = [
            [
                'service_code' => 'SRV-DOC-JBR-001',
                'service_category_id' => $serviceCategories['doctor']->id,
                'name' => 'Dokter Datang ke Rumah',
                'slug' => 'dokter-datang-ke-rumah',
                'service_type' => 'homecare',
                'service_mode' => 'visit',
                'category' => 'Doctor',
                'description' => 'Kunjungan dokter ke rumah untuk pemeriksaan dasar, evaluasi keluhan, dan rekomendasi terapi awal.',
                'base_price' => 225000,
                'duration_minutes' => 60,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 10,
                'is_active' => true,
                'is_homecare' => true,
            ],
            [
                'service_code' => 'SRV-DOC-JBR-002',
                'service_category_id' => $serviceCategories['doctor']->id,
                'name' => 'Konsultasi Video Dokter',
                'slug' => 'konsultasi-video-dokter',
                'service_type' => 'consultation',
                'service_mode' => 'video',
                'category' => 'Doctor',
                'description' => 'Konsultasi dokter melalui video call untuk asesmen awal, edukasi, dan tindak lanjut keluhan pasien.',
                'base_price' => 300000,
                'duration_minutes' => 30,
                'requires_address' => false,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 20,
                'is_active' => true,
                'is_homecare' => false,
            ],
            [
                'service_code' => 'SRV-NRS-JBR-001',
                'service_category_id' => $serviceCategories['nurse']->id,
                'name' => 'Pasang Infus',
                'slug' => 'pasang-infus',
                'service_type' => 'procedure',
                'service_mode' => 'visit',
                'category' => 'Nurse',
                'description' => 'Layanan pemasangan infus di rumah oleh perawat terverifikasi sesuai kebutuhan pasien.',
                'base_price' => 185000,
                'duration_minutes' => 90,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 30,
                'is_active' => true,
                'is_homecare' => true,
            ],
            [
                'service_code' => 'SRV-NRS-JBR-002',
                'service_category_id' => $serviceCategories['nurse']->id,
                'name' => 'Perawatan Luka',
                'slug' => 'perawatan-luka',
                'service_type' => 'procedure',
                'service_mode' => 'visit',
                'category' => 'Nurse',
                'description' => 'Perawatan luka di rumah untuk luka operasi, luka diabetes, dan luka kronis dengan teknik steril.',
                'base_price' => 210000,
                'duration_minutes' => 75,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 40,
                'is_active' => true,
                'is_homecare' => true,
            ],
            [
                'service_code' => 'SRV-NRS-JBR-003',
                'service_category_id' => $serviceCategories['nurse']->id,
                'name' => 'Pasang Kateter',
                'slug' => 'pasang-kateter',
                'service_type' => 'procedure',
                'service_mode' => 'visit',
                'category' => 'Nurse',
                'description' => 'Pemasangan atau penggantian kateter di rumah oleh perawat terverifikasi.',
                'base_price' => 240000,
                'duration_minutes' => 75,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 50,
                'is_active' => true,
                'is_homecare' => true,
            ],
            [
                'service_code' => 'SRV-CGV-JBR-001',
                'service_category_id' => $serviceCategories['caregiver']->id,
                'name' => 'Rawat Lansia 12 Jam',
                'slug' => 'rawat-lansia-12-jam',
                'service_type' => 'caregiver',
                'service_mode' => 'visit',
                'category' => 'Caregiver',
                'description' => 'Pendampingan lansia di rumah selama 12 jam, termasuk monitoring dasar dan bantuan aktivitas harian.',
                'base_price' => 350000,
                'duration_minutes' => 720,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 60,
                'is_active' => true,
                'is_homecare' => true,
            ],
            [
                'service_code' => 'SRV-MDW-JBR-001',
                'service_category_id' => $serviceCategories['midwife']->id,
                'name' => 'Pemeriksaan Kehamilan di Rumah',
                'slug' => 'pemeriksaan-kehamilan-di-rumah',
                'service_type' => 'homecare',
                'service_mode' => 'visit',
                'category' => 'Midwife',
                'description' => 'Pemeriksaan kehamilan dasar di rumah oleh bidan terverifikasi.',
                'base_price' => 220000,
                'duration_minutes' => 60,
                'requires_address' => true,
                'requires_schedule' => true,
                'requires_matchmaking' => true,
                'sort_order' => 70,
                'is_active' => true,
                'is_homecare' => true,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['service_code' => $service['service_code']],
                $service
            );
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

        $doctorHomecareService = Service::where('service_code', 'SRV-DOC-JBR-001')->firstOrFail();
        $doctorProcedureService = Service::where('service_code', 'SRV-DOC-JBR-002')->firstOrFail();
        $nurseHomecareService = Service::where('service_code', 'SRV-NRS-JBR-001')->firstOrFail();
        $nurseWoundCareService = Service::where('service_code', 'SRV-NRS-JBR-002')->firstOrFail();
        $nurseCatheterService = Service::where('service_code', 'SRV-NRS-JBR-003')->firstOrFail();
        $caregiverElderlyService = Service::where('service_code', 'SRV-CGV-JBR-001')->firstOrFail();

        $partnerServices = [
            [
                'service_id' => $doctorHomecareService->id,
                'partner_user_id' => $doctor->id,
                'price' => 235000,
                'coverage_radius_km' => 12,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Dokter umum untuk home visit area Jember kota.',
            ],
            [
                'service_id' => $doctorProcedureService->id,
                'partner_user_id' => $doctor->id,
                'price' => 325000,
                'coverage_radius_km' => 10,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Dokter melayani tindakan medis ringan homecare.',
            ],
            [
                'service_id' => $nurseHomecareService->id,
                'partner_user_id' => $nurse->id,
                'price' => 185000,
                'coverage_radius_km' => 15,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Perawat aktif untuk layanan homecare umum.',
            ],
            [
                'service_id' => $nurseWoundCareService->id,
                'partner_user_id' => $nurse->id,
                'price' => 210000,
                'coverage_radius_km' => 15,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Perawat rawat luka aktif area Jember.',
            ],
            [
                'service_id' => $nurseCatheterService->id,
                'partner_user_id' => $nurse->id,
                'price' => 240000,
                'coverage_radius_km' => 15,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Perawat tersedia untuk pemasangan kateter area Jember.',
            ],
            [
                'service_id' => $caregiverElderlyService->id,
                'partner_user_id' => $nurse->id,
                'price' => 350000,
                'coverage_radius_km' => 12,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Contoh layanan caregiver lansia yang ditangani mitra perawat.',
            ],
        ];

        foreach ($seededAdditionalNurseUsers as $index => $additionalNurseUser) {
            $partnerServices[] = [
                'service_id' => $nurseHomecareService->id,
                'partner_user_id' => $additionalNurseUser->id,
                'price' => 180000 + (($index + 1) * 5000),
                'coverage_radius_km' => 10 + ($index % 4) * 2,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Perawat homecare area Jember dan sekitarnya.',
            ];

            if ($index % 2 === 0) {
                $partnerServices[] = [
                    'service_id' => $nurseWoundCareService->id,
                    'partner_user_id' => $additionalNurseUser->id,
                    'price' => 205000 + (($index + 1) * 5000),
                    'coverage_radius_km' => 10 + ($index % 3) * 3,
                    'is_active' => true,
                    'is_verified' => true,
                    'is_available' => true,
                    'notes' => 'Perawat rawat luka area Jember dan sekitarnya.',
                ];
            }
        }

        foreach ($seededAdditionalDoctorUsers as $index => $additionalDoctor) {
            $partnerServices[] = [
                'service_id' => $doctorHomecareService->id,
                'partner_user_id' => $additionalDoctor['user']->id,
                'price' => 220000 + (($index + 1) * 5000),
                'coverage_radius_km' => 8 + ($index % 4) * 2,
                'is_active' => true,
                'is_verified' => true,
                'is_available' => true,
                'notes' => 'Dokter spesialis melayani konsultasi dan home visit area Jember.',
            ];

            if ($index % 3 === 0) {
                $partnerServices[] = [
                    'service_id' => $doctorProcedureService->id,
                    'partner_user_id' => $additionalDoctor['user']->id,
                    'price' => 300000 + (($index + 1) * 7500),
                    'coverage_radius_km' => 8 + ($index % 3) * 2,
                    'is_active' => true,
                    'is_verified' => true,
                    'is_available' => true,
                    'notes' => 'Dokter spesialis tersedia untuk tindakan medis ringan sesuai asesmen.',
                ];
            }
        }

        foreach ($partnerServices as $partnerService) {
            PartnerService::updateOrCreate(
                [
                    'service_id' => $partnerService['service_id'],
                    'partner_user_id' => $partnerService['partner_user_id'],
                ],
                $partnerService
            );
        }

        ServiceBooking::updateOrCreate(
            ['booking_code' => 'SVB-JBR-0001'],
            [
                'service_id' => $doctorHomecareService->id,
                'patient_user_id' => $patientOne->id,
                'assigned_partner_user_id' => $doctor->id,
                'patient_address_id' => $addressOne->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays(2),
                'started_at' => now()->subDays(2)->addMinutes(10),
                'completed_at' => now()->subDays(2)->addMinutes(70),
                'total_amount' => 235000,
                'notes' => 'Kunjungan dokter home visit untuk evaluasi demam dan batuk.',
            ]
        );

        ServiceBooking::updateOrCreate(
            ['booking_code' => 'SVB-JBR-0002'],
            [
                'service_id' => $nurseHomecareService->id,
                'patient_user_id' => $patientTwo->id,
                'assigned_partner_user_id' => $nurse->id,
                'patient_address_id' => $addressTwo->id,
                'status' => 'scheduled',
                'scheduled_at' => now()->addDay()->setTime(9, 0),
                'started_at' => null,
                'completed_at' => null,
                'total_amount' => 185000,
                'notes' => 'Booking perawat home visit untuk kontrol kondisi pasien pasca perawatan.',
            ]
        );

        ServiceBooking::updateOrCreate(
            ['booking_code' => 'SVB-JBR-0003'],
            [
                'service_id' => $nurseWoundCareService->id,
                'patient_user_id' => $patientOne->id,
                'assigned_partner_user_id' => $nurse->id,
                'patient_address_id' => $addressOne->id,
                'status' => 'confirmed',
                'scheduled_at' => now()->addHours(6),
                'started_at' => null,
                'completed_at' => null,
                'total_amount' => 210000,
                'notes' => 'Rawat luka lanjutan di rumah pasien area Kaliwates.',
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

        $paracetamol = Product::where('pharmacy_id', $pharmacy->id)->where('sku', 'JBR-OBT-001')->firstOrFail();
        $amoxicillin = Product::where('pharmacy_id', $pharmacy->id)->where('sku', 'JBR-OBT-002')->firstOrFail();
        $vitaminC = Product::where('pharmacy_id', $pharmacy->id)->where('sku', 'JBR-OBT-003')->firstOrFail();

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

        $orderOneData = [
            'patient_user_id' => $patientOne->id,
            'pharmacy_id' => $pharmacy->id,
            'patient_address_id' => $addressOne->id,
            'prescription_id' => $prescription->id,
            'order_type' => 'resep',
            'status' => 'shipped',
            'subtotal' => 47000,
            'shipping_cost' => 10000,
            'total_amount' => 57000,
            'notes' => 'Pengantaran ke area Kaliwates, Jember.',
            'ordered_at' => now()->subHours(6),
        ];

        if (Schema::hasColumn('orders', 'pharmacy_user_id')) {
            $orderOneData['pharmacy_user_id'] = $pharmacyOwner->id;
        }

        $orderOne = Order::unguarded(fn () => Order::updateOrCreate(
            ['order_code' => 'ORD-JBR-0001'],
            $orderOneData
        ));

        OrderItem::updateOrCreate(
            ['order_id' => $orderOne->id, 'product_id' => $paracetamol->id],
            [
                'product_name' => $paracetamol->name,
                'unit_price' => $paracetamol->price,
                'unit_cost' => $paracetamol->cost_price,
                'quantity' => 1,
                'total_price' => $paracetamol->price,
                'total_cost' => $paracetamol->cost_price,
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderOne->id, 'product_id' => $amoxicillin->id],
            [
                'product_name' => $amoxicillin->name,
                'unit_price' => $amoxicillin->price,
                'unit_cost' => $amoxicillin->cost_price,
                'quantity' => 1,
                'total_price' => $amoxicillin->price,
                'total_cost' => $amoxicillin->cost_price,
            ]
        );

        $orderTwoData = [
            'patient_user_id' => $patientTwo->id,
            'pharmacy_id' => $pharmacy->id,
            'patient_address_id' => $addressTwo->id,
            'prescription_id' => null,
            'order_type' => 'non_resep',
            'status' => 'delivered',
            'subtotal' => 83000,
            'shipping_cost' => 12000,
            'total_amount' => 95000,
            'notes' => 'Pesanan vitamin dan alat kesehatan area Sumbersari, Jember.',
            'ordered_at' => now()->subDay(),
        ];

        if (Schema::hasColumn('orders', 'pharmacy_user_id')) {
            $orderTwoData['pharmacy_user_id'] = $pharmacyOwner->id;
        }

        $orderTwo = Order::unguarded(fn () => Order::updateOrCreate(
            ['order_code' => 'ORD-JBR-0002'],
            $orderTwoData
        ));

        OrderItem::updateOrCreate(
            ['order_id' => $orderTwo->id, 'product_id' => $vitaminC->id],
            [
                'product_name' => $vitaminC->name,
                'unit_price' => $vitaminC->price,
                'unit_cost' => $vitaminC->cost_price,
                'quantity' => 1,
                'total_price' => $vitaminC->price,
                'total_cost' => $vitaminC->cost_price,
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orderTwo->id, 'product_id' => Product::where('pharmacy_id', $pharmacy->id)->where('sku', 'JBR-OBT-004')->firstOrFail()->id],
            [
                'product_name' => 'Termometer Digital',
                'unit_price' => 55000,
                'unit_cost' => 40000,
                'quantity' => 1,
                'total_price' => 55000,
                'total_cost' => 40000,
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

    private function seedPartnerDocuments(int $userId, string $partnerName, string $licenseNumber): array
    {
        $disk = Storage::disk('private');

        $basePath = "partners/{$userId}";
        $nameSlug = Str::slug($partnerName);
        $strPath = "{$basePath}/str-{$nameSlug}.png";
        $ktpPath = "{$basePath}/ktp-{$nameSlug}.png";

        $nik = $this->seedDocumentNikForUser($userId);

        if (! $disk->exists($ktpPath)) {
            $disk->put($ktpPath, $this->renderSeedKtpPng($partnerName, $nik));
        }

        if (! $disk->exists($strPath)) {
            $disk->put($strPath, $this->renderSeedStrPng($partnerName, $licenseNumber));
        }

        return [
            'str_photo_path' => $strPath,
            'ktp_photo_path' => $ktpPath,
        ];
    }

    private function seedDocumentNikForUser(int $userId): string
    {
        $prefix = '3509';
        $body = str_pad((string) ($userId % 1000000000000), 12, '0', STR_PAD_LEFT);

        return $prefix . substr($body, -12);
    }

    private function renderSeedKtpPng(string $name, string $nik): string
    {
        if (! $this->canRenderPng()) {
            return $this->renderFallbackTinyPng();
        }

        $width = 1400;
        $height = 880;

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $bg1 = imagecolorallocate($image, 16, 185, 129);   // emerald
        $bg2 = imagecolorallocate($image, 59, 130, 246);   // blue
        $paper = imagecolorallocate($image, 255, 255, 255);
        $paperBorder = imagecolorallocate($image, 226, 232, 240);
        $ink = imagecolorallocate($image, 15, 23, 42);
        $muted = imagecolorallocate($image, 51, 65, 85);
        $muted2 = imagecolorallocate($image, 100, 116, 139);
        $photoBg = imagecolorallocate($image, 226, 232, 240);
        $photoInk = imagecolorallocate($image, 71, 85, 105);
        $danger = imagecolorallocate($image, 239, 68, 68);

        $this->drawDiagonalGradient($image, 0, 0, $width, $height, $bg1, $bg2);
        $this->drawNoise($image, 2200, 25);

        // Card
        $this->drawRoundedRect($image, 70, 70, $width - 70, $height - 70, 32, $paper);
        $this->drawRoundedRectOutline($image, 70, 70, $width - 70, $height - 70, 32, $paperBorder);

        // Header band
        $headerColor = imagecolorallocate($image, 15, 118, 110);
        $this->drawRoundedRect($image, 95, 95, $width - 95, 200, 22, $headerColor);
        imagestring($image, 5, 130, 125, 'KARTU TANDA PENDUDUK', $paper);
        imagestring($image, 3, 130, 160, 'Contoh dokumen (seed only)', $paper);

        // Fake emblem
        $emblemX = $width - 220;
        $emblemY = 115;
        imagefilledellipse($image, $emblemX, $emblemY, 80, 80, $paper);
        imageellipse($image, $emblemX, $emblemY, 80, 80, $paperBorder);
        imagestring($image, 2, $emblemX - 22, $emblemY - 6, 'RI', $ink);

        // Photo area with silhouette
        $photoLeft = $width - 370;
        $photoTop = 240;
        $photoRight = $width - 130;
        $photoBottom = 520;
        $this->drawRoundedRect($image, $photoLeft, $photoTop, $photoRight, $photoBottom, 18, $photoBg);
        $this->drawRoundedRectOutline($image, $photoLeft, $photoTop, $photoRight, $photoBottom, 18, $paperBorder);
        imagefilledellipse($image, (int) (($photoLeft + $photoRight) / 2), $photoTop + 90, 90, 90, $photoInk);
        imagefilledellipse($image, (int) (($photoLeft + $photoRight) / 2), $photoTop + 200, 160, 160, $photoInk);
        imagestring($image, 2, $photoLeft + 18, $photoBottom - 26, 'FOTO', $muted2);

        // Data fields
        $xLabel = 130;
        $xValue = 360;
        $y = 260;
        $row = 58;

        imagestring($image, 4, $xLabel, $y, 'NIK', $muted);
        imagestring($image, 4, $xValue, $y, ': ' . $nik, $ink);
        $y += $row;

        imagestring($image, 4, $xLabel, $y, 'Nama', $muted);
        imagestring($image, 4, $xValue, $y, ': ' . $name, $ink);
        $y += $row;

        imagestring($image, 4, $xLabel, $y, 'Alamat', $muted);
        imagestring($image, 4, $xValue, $y, ': Jember, Jawa Timur', $ink);
        $y += $row;

        imagestring($image, 4, $xLabel, $y, 'Berlaku', $muted);
        imagestring($image, 4, $xValue, $y, ': SEUMUR HIDUP', $danger);
        $y += (int) ($row * 1.2);

        // QR + barcode
        $this->drawQrLike($image, 130, 520, 190);
        $this->drawBarcodeLike($image, 360, 560, 640, 110);

        // Signature scribble
        $this->drawSignatureLike($image, 1020, 635, 270, 70, $photoInk);
        imagestring($image, 2, 980, 710, 'seed-doc:ktp', $muted2);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png ?: $this->renderFallbackTinyPng();
    }

    private function renderSeedStrPng(string $name, string $licenseNumber): string
    {
        if (! $this->canRenderPng()) {
            return $this->renderFallbackTinyPng();
        }

        $width = 1400;
        $height = 990;

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $bg = imagecolorallocate($image, 241, 245, 249);
        $paper = imagecolorallocate($image, 255, 255, 255);
        $border = imagecolorallocate($image, 226, 232, 240);
        $ink = imagecolorallocate($image, 15, 23, 42);
        $muted = imagecolorallocate($image, 51, 65, 85);
        $muted2 = imagecolorallocate($image, 100, 116, 139);
        $navy = imagecolorallocate($image, 15, 23, 42);
        $seal = imagecolorallocate($image, 14, 165, 233);

        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
        $this->drawNoise($image, 2500, 18);

        $this->drawRoundedRect($image, 80, 70, $width - 80, $height - 70, 28, $paper);
        $this->drawRoundedRectOutline($image, 80, 70, $width - 80, $height - 70, 28, $border);

        // Header
        $this->drawRoundedRect($image, 110, 100, $width - 110, 240, 20, $navy);
        imagestring($image, 5, 150, 140, 'SURAT TANDA REGISTRASI', $paper);
        imagestring($image, 3, 150, 180, 'Contoh dokumen (seed only)', $paper);

        // Watermark band
        $wm = imagecolorallocatealpha($image, 148, 163, 184, 85);
        for ($i = -300; $i < $width; $i += 220) {
            imagestring($image, 5, $i, 420, 'SEED-ONLY', $wm);
        }

        // Main fields
        $xLabel = 150;
        $xValue = 390;
        $y = 310;
        $row = 62;

        imagestring($image, 4, $xLabel, $y, 'Nama', $muted);
        imagestring($image, 4, $xValue, $y, ': ' . $name, $ink);
        $y += $row;

        imagestring($image, 4, $xLabel, $y, 'Nomor', $muted);
        imagestring($image, 4, $xValue, $y, ': ' . $licenseNumber, $ink);
        $y += $row;

        imagestring($image, 4, $xLabel, $y, 'Status', $muted);
        imagestring($image, 4, $xValue, $y, ': TERDAFTAR', $ink);
        $y += $row;

        imagestring($image, 4, $xLabel, $y, 'Diterbitkan', $muted);
        imagestring($image, 4, $xValue, $y, ': Seeder Generator', $ink);

        // QR and barcode
        $this->drawQrLike($image, $width - 360, 300, 200);
        $this->drawBarcodeLike($image, 150, 700, 760, 120);

        // Stamp + signature
        imagefilledellipse($image, $width - 300, 700, 220, 220, $seal);
        imageellipse($image, $width - 300, 700, 220, 220, $border);
        imagestring($image, 4, $width - 358, 690, 'STEMPEL', $paper);
        $this->drawSignatureLike($image, $width - 560, 760, 360, 80, $muted);
        imagestring($image, 2, 140, $height - 120, 'seed-doc:str', $muted2);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png ?: $this->renderFallbackTinyPng();
    }

    private function canRenderPng(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagepng')
            && function_exists('imagecolorallocate')
            && function_exists('imagestring');
    }

    private function renderFallbackTinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2GZ6kAAAAASUVORK5CYII=',
            true
        ) ?: '';
    }

    private function drawDiagonalGradient($image, int $x1, int $y1, int $x2, int $y2, int $c1, int $c2): void
    {
        $w = max(1, $x2 - $x1);
        $h = max(1, $y2 - $y1);

        $r1 = ($c1 >> 16) & 0xFF;
        $g1 = ($c1 >> 8) & 0xFF;
        $b1 = $c1 & 0xFF;

        $r2 = ($c2 >> 16) & 0xFF;
        $g2 = ($c2 >> 8) & 0xFF;
        $b2 = $c2 & 0xFF;

        $steps = $w + $h;
        for ($i = 0; $i <= $steps; $i++) {
            $t = $steps === 0 ? 0 : $i / $steps;
            $r = (int) round($r1 + ($r2 - $r1) * $t);
            $g = (int) round($g1 + ($g2 - $g1) * $t);
            $b = (int) round($b1 + ($b2 - $b1) * $t);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, $x1, $y1 + $i, $x1 + $i, $y1, $color);
        }
    }

    private function drawNoise($image, int $dots, int $alpha = 30): void
    {
        $w = imagesx($image);
        $h = imagesy($image);

        for ($i = 0; $i < $dots; $i++) {
            $x = random_int(0, $w - 1);
            $y = random_int(0, $h - 1);
            $c = imagecolorallocatealpha(
                $image,
                random_int(0, 255),
                random_int(0, 255),
                random_int(0, 255),
                max(0, min(127, $alpha))
            );
            imagesetpixel($image, $x, $y, $c);
        }
    }

    private function drawRoundedRect($image, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = max(0, min($r, (int) floor(min(($x2 - $x1), ($y2 - $y1)) / 2)));

        imagefilledrectangle($image, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $r, $x2, $y2 - $r, $color);

        imagefilledellipse($image, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($image, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($image, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($image, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    private function drawRoundedRectOutline($image, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = max(0, min($r, (int) floor(min(($x2 - $x1), ($y2 - $y1)) / 2)));

        imageline($image, $x1 + $r, $y1, $x2 - $r, $y1, $color);
        imageline($image, $x1 + $r, $y2, $x2 - $r, $y2, $color);
        imageline($image, $x1, $y1 + $r, $x1, $y2 - $r, $color);
        imageline($image, $x2, $y1 + $r, $x2, $y2 - $r, $color);

        imagearc($image, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color);
        imagearc($image, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color);
        imagearc($image, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color);
        imagearc($image, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color);
    }

    private function drawQrLike($image, int $x, int $y, int $size): void
    {
        $bg = imagecolorallocate($image, 255, 255, 255);
        $fg = imagecolorallocate($image, 15, 23, 42);
        $border = imagecolorallocate($image, 226, 232, 240);

        $this->drawRoundedRect($image, $x, $y, $x + $size, $y + $size, 14, $bg);
        $this->drawRoundedRectOutline($image, $x, $y, $x + $size, $y + $size, 14, $border);

        $cell = max(4, (int) floor($size / 29));
        $grid = (int) floor($size / $cell);

        for ($gy = 0; $gy < $grid; $gy++) {
            for ($gx = 0; $gx < $grid; $gx++) {
                // Keep finder patterns clear-ish
                $inFinder = ($gx < 7 && $gy < 7) || ($gx > $grid - 8 && $gy < 7) || ($gx < 7 && $gy > $grid - 8);
                if ($inFinder) {
                    continue;
                }

                if (random_int(0, 100) < 45) {
                    imagefilledrectangle(
                        $image,
                        $x + 10 + $gx * $cell,
                        $y + 10 + $gy * $cell,
                        $x + 10 + ($gx + 1) * $cell - 1,
                        $y + 10 + ($gy + 1) * $cell - 1,
                        $fg
                    );
                }
            }
        }

        $this->drawQrFinder($image, $x + 14, $y + 14, $cell, $fg, $bg);
        $this->drawQrFinder($image, $x + $size - 14 - 7 * $cell, $y + 14, $cell, $fg, $bg);
        $this->drawQrFinder($image, $x + 14, $y + $size - 14 - 7 * $cell, $cell, $fg, $bg);
    }

    private function drawQrFinder($image, int $x, int $y, int $cell, int $fg, int $bg): void
    {
        imagefilledrectangle($image, $x, $y, $x + 7 * $cell, $y + 7 * $cell, $fg);
        imagefilledrectangle($image, $x + $cell, $y + $cell, $x + 6 * $cell, $y + 6 * $cell, $bg);
        imagefilledrectangle($image, $x + 2 * $cell, $y + 2 * $cell, $x + 5 * $cell, $y + 5 * $cell, $fg);
    }

    private function drawBarcodeLike($image, int $x, int $y, int $width, int $height): void
    {
        $bg = imagecolorallocate($image, 255, 255, 255);
        $fg = imagecolorallocate($image, 15, 23, 42);
        $border = imagecolorallocate($image, 226, 232, 240);

        $this->drawRoundedRect($image, $x, $y, $x + $width, $y + $height, 12, $bg);
        $this->drawRoundedRectOutline($image, $x, $y, $x + $width, $y + $height, 12, $border);

        $cursor = $x + 12;
        $maxX = $x + $width - 12;
        while ($cursor < $maxX) {
            $barWidth = random_int(1, 4);
            $barHeight = $height - random_int(18, 34);
            imagefilledrectangle($image, $cursor, $y + 10, min($cursor + $barWidth, $maxX), $y + 10 + $barHeight, $fg);
            $cursor += $barWidth + random_int(1, 3);
        }
    }

    private function drawSignatureLike($image, int $x, int $y, int $width, int $height, int $color): void
    {
        $points = [];
        $segments = 18;
        $baseY = $y + (int) ($height / 2);

        for ($i = 0; $i <= $segments; $i++) {
            $px = $x + (int) round(($width / $segments) * $i);
            $py = $baseY + random_int(-(int) ($height / 3), (int) ($height / 3));
            $points[] = [$px, $py];
        }

        imagesetthickness($image, 3);
        for ($i = 0; $i < count($points) - 1; $i++) {
            imageline($image, $points[$i][0], $points[$i][1], $points[$i + 1][0], $points[$i + 1][1], $color);
        }
        imagesetthickness($image, 1);
    }
}
