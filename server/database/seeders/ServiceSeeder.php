<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\ServicePriceHistory;
use App\Models\ServiceStatusHistory;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['code' => 'GR_KHAM', 'name' => 'Khám & chẩn đoán', 'display_order' => 1],
            ['code' => 'GR_DTCB', 'name' => 'Điều trị cơ bản', 'display_order' => 2],
            ['code' => 'GR_PH', 'name' => 'Phục hình', 'display_order' => 3],
            ['code' => 'GR_CN', 'name' => 'Chỉnh nha', 'display_order' => 4],
            ['code' => 'GR_IM', 'name' => 'Implant', 'display_order' => 5],
            ['code' => 'GR_TM', 'name' => 'Thẩm mỹ', 'display_order' => 6],
        ];
        foreach ($groups as $g) {
            ServiceGroup::firstOrCreate(['code' => $g['code']], $g + ['is_active' => true]);
        }

        $specialties = [
            ['code' => 'SP_NTQ', 'name' => 'Nha tổng quát'],
            ['code' => 'SP_PH', 'name' => 'Phục hình'],
            ['code' => 'SP_CN', 'name' => 'Chỉnh nha'],
            ['code' => 'SP_IM', 'name' => 'Implant'],
            ['code' => 'SP_NC', 'name' => 'Nha chu'],
            ['code' => 'SP_TM', 'name' => 'Thẩm mỹ'],
        ];
        foreach ($specialties as $s) {
            Specialty::firstOrCreate(['code' => $s['code']], $s + ['is_active' => true]);
        }

        $byGroup = ServiceGroup::pluck('id', 'code');
        $bySpec = Specialty::pluck('id', 'code');

        $services = [
            [
                'service_code' => 'DV0001',
                'name' => 'Khám và tư vấn tổng quát',
                'group' => 'GR_KHAM',
                'description' => 'Khám tổng quát răng miệng và tư vấn kế hoạch điều trị.',
                'price' => 200000,
                'duration_minutes' => 30,
                'commission_rate' => 5,
                'status' => Service::STATUS_ACTIVE,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_NTQ',
                'specialties' => ['SP_NTQ'],
            ],
            [
                'service_code' => 'DV0002',
                'name' => 'Cạo vôi răng',
                'group' => 'GR_DTCB',
                'price' => 300000,
                'duration_minutes' => 30,
                'commission_rate' => 10,
                'status' => Service::STATUS_ACTIVE,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_NTQ',
                'specialties' => ['SP_NTQ', 'SP_NC'],
            ],
            [
                'service_code' => 'DV0003',
                'name' => 'Trám răng thẩm mỹ',
                'group' => 'GR_DTCB',
                'price' => 450000,
                'duration_minutes' => 45,
                'commission_rate' => 12,
                'status' => Service::STATUS_ACTIVE,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_NTQ',
                'specialties' => ['SP_NTQ', 'SP_TM'],
            ],
            [
                'service_code' => 'DV0004',
                'name' => 'Bọc răng sứ kim loại',
                'group' => 'GR_PH',
                'price' => 1800000,
                'duration_minutes' => 90,
                'commission_rate' => 15,
                'status' => Service::STATUS_ACTIVE,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_PH',
                'specialties' => ['SP_PH'],
            ],
            [
                'service_code' => 'DV0005',
                'name' => 'Niềng răng mắc cài kim loại',
                'group' => 'GR_CN',
                'price' => 25000000,
                'duration_minutes' => 60,
                'commission_rate' => 18,
                'status' => Service::STATUS_ACTIVE,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_CN',
                'specialties' => ['SP_CN'],
            ],
            [
                'service_code' => 'DV0006',
                'name' => 'Cấy ghép Implant trụ Hàn Quốc',
                'group' => 'GR_IM',
                'price' => 18000000,
                'duration_minutes' => 120,
                'commission_rate' => 20,
                'status' => Service::STATUS_ACTIVE,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_IM',
                'specialties' => ['SP_IM', 'SP_PH'],
            ],
            [
                'service_code' => 'DV0007',
                'name' => 'Tẩy trắng răng tại phòng khám',
                'group' => 'GR_TM',
                'price' => 2500000,
                'duration_minutes' => 60,
                'commission_rate' => 15,
                'status' => Service::STATUS_HIDDEN,
                'visibility' => Service::VISIBILITY_PUBLIC,
                'primary' => 'SP_TM',
                'specialties' => ['SP_TM', 'SP_NTQ'],
            ],
            [
                'service_code' => 'DV0008',
                'name' => 'Khám sàng lọc nội bộ nhân viên',
                'group' => 'GR_KHAM',
                'price' => 0,
                'duration_minutes' => 20,
                'commission_rate' => 0,
                'status' => Service::STATUS_DRAFT,
                'visibility' => Service::VISIBILITY_INTERNAL,
                'primary' => 'SP_NTQ',
                'specialties' => ['SP_NTQ'],
            ],
        ];

        foreach ($services as $row) {
            $svc = Service::firstOrCreate(
                ['service_code' => $row['service_code']],
                [
                    'service_group_id' => $byGroup[$row['group']] ?? null,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'price' => $row['price'],
                    'duration_minutes' => $row['duration_minutes'],
                    'commission_rate' => $row['commission_rate'],
                    'status' => $row['status'],
                    'visibility' => $row['visibility'],
                ]
            );

            $sync = [];
            foreach ($row['specialties'] as $code) {
                $sync[$bySpec[$code]] = ['is_primary' => $code === $row['primary']];
            }
            $svc->specialties()->sync($sync);

            ServicePriceHistory::firstOrCreate(
                ['service_id' => $svc->id, 'new_price' => $row['price']],
                [
                    'old_price' => null,
                    'effective_date' => Carbon::now()->toDateString(),
                    'reason' => 'Khoi tao du lieu',
                    'created_at' => now(),
                ]
            );

            ServiceStatusHistory::firstOrCreate(
                ['service_id' => $svc->id, 'new_status' => $row['status']],
                [
                    'old_status' => null,
                    'reason' => 'Khoi tao du lieu',
                    'created_at' => now(),
                ]
            );
        }
    }
}
