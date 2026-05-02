<?php

namespace Database\Seeders;

use App\Models\ToothStatus;
use App\Models\ToothStatusGroup;
use Illuminate\Database\Seeder;

class ToothStatusSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['code' => 'NORMAL', 'name' => 'Răng bình thường', 'display_order' => 1],
            ['code' => 'CARIES', 'name' => 'Răng sâu', 'display_order' => 2],
            ['code' => 'PULP', 'name' => 'Viêm tủy', 'display_order' => 3],
            ['code' => 'POST_TREATMENT', 'name' => 'Sau điều trị', 'display_order' => 4],
            ['code' => 'MISSING', 'name' => 'Mất răng', 'display_order' => 5],
            ['code' => 'OTHER', 'name' => 'Khác', 'display_order' => 6],
        ];

        $groupIdByCode = [];
        foreach ($groups as $group) {
            $record = ToothStatusGroup::firstOrCreate(
                ['code' => $group['code']],
                [
                    'name' => $group['name'],
                    'display_order' => $group['display_order'],
                    'is_active' => true,
                ],
            );
            $groupIdByCode[$group['code']] = $record->id;
        }

        $statuses = [
            ['code' => 'STT001', 'name' => 'Răng khỏe mạnh', 'group' => 'NORMAL', 'color' => '#22C55E', 'icon' => '🦷', 'description' => 'Răng không sâu, không viêm, mô cứng và mô mềm xung quanh bình thường.'],
            ['code' => 'STT002', 'name' => 'Sâu men', 'group' => 'CARIES', 'color' => '#FACC15', 'icon' => '🦷', 'description' => 'Sâu ở men răng, chưa vào ngà.'],
            ['code' => 'STT003', 'name' => 'Sâu ngà', 'group' => 'CARIES', 'color' => '#FB923C', 'icon' => '🦷', 'description' => 'Sâu đã ăn vào lớp ngà, có thể đau khi nhai.'],
            ['code' => 'STT004', 'name' => 'Viêm tủy', 'group' => 'PULP', 'color' => '#EF4444', 'icon' => '🦷', 'description' => 'Tủy răng bị viêm, đau nhức, cần điều trị nội nha.'],
            ['code' => 'STT005', 'name' => 'Răng đã điều trị tủy', 'group' => 'POST_TREATMENT', 'color' => '#2563EB', 'icon' => '🦷', 'description' => 'Răng đã được lấy tủy, hàn ống tủy.'],
            ['code' => 'STT006', 'name' => 'Răng đã trám', 'group' => 'POST_TREATMENT', 'color' => '#A855F7', 'icon' => '⚪', 'description' => 'Răng có miếng trám composite hoặc amalgam.'],
            ['code' => 'STT007', 'name' => 'Răng đã bọc sứ', 'group' => 'POST_TREATMENT', 'color' => '#0EA5E9', 'icon' => '⚪', 'description' => 'Răng được bọc mão sứ phục hình.'],
            ['code' => 'STT008', 'name' => 'Răng đã nhổ', 'group' => 'MISSING', 'color' => '#9CA3AF', 'icon' => '―', 'description' => 'Răng đã được nhổ, vị trí trống.'],
            ['code' => 'STT009', 'name' => 'Răng cần nhổ', 'group' => 'CARIES', 'color' => '#DC2626', 'icon' => '🦷', 'description' => 'Răng tổn thương nặng, có chỉ định nhổ.'],
            ['code' => 'STT010', 'name' => 'Răng implant', 'group' => 'POST_TREATMENT', 'color' => '#6366F1', 'icon' => '🔩', 'description' => 'Răng đã được cấy ghép implant.'],
            ['code' => 'STT011', 'name' => 'Răng nứt', 'group' => 'OTHER', 'color' => '#F97316', 'icon' => '🦷', 'description' => 'Thân răng có vết nứt nhỏ chưa gãy.'],
            ['code' => 'STT012', 'name' => 'Răng vỡ lớn', 'group' => 'OTHER', 'color' => '#B91C1C', 'icon' => '🦷', 'description' => 'Vỡ thân răng diện tích lớn, lộ ngà.'],
            ['code' => 'STT013', 'name' => 'Răng mọc lệch', 'group' => 'OTHER', 'color' => '#0891B2', 'icon' => '🦷', 'description' => 'Răng mọc sai vị trí, cần chỉnh nha.'],
            ['code' => 'STT014', 'name' => 'Răng sữa', 'group' => 'NORMAL', 'color' => '#84CC16', 'icon' => '🦷', 'description' => 'Răng sữa của trẻ em, theo dõi rụng.'],
            ['code' => 'STT015', 'name' => 'Răng khôn mọc thẳng', 'group' => 'NORMAL', 'color' => '#16A34A', 'icon' => '🦷', 'description' => 'Răng số 8 mọc thẳng, không gây biến chứng.'],
            ['code' => 'STT016', 'name' => 'Răng khôn mọc lệch', 'group' => 'OTHER', 'color' => '#7C3AED', 'icon' => '🦷', 'description' => 'Răng số 8 mọc lệch, có chỉ định nhổ.'],
            ['code' => 'STT017', 'name' => 'Sâu thứ phát', 'group' => 'CARIES', 'color' => '#F59E0B', 'icon' => '🦷', 'description' => 'Sâu tái phát ở răng đã trám.'],
            ['code' => 'STT018', 'name' => 'Răng nhạy cảm', 'group' => 'OTHER', 'color' => '#D946EF', 'icon' => '🦷', 'description' => 'Răng ê buốt khi ăn lạnh, ngọt hoặc chua.'],
        ];

        foreach ($statuses as $i => $row) {
            ToothStatus::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'tooth_status_group_id' => $groupIdByCode[$row['group']],
                    'color' => $row['color'],
                    'icon' => $row['icon'] ?? null,
                    'description' => $row['description'] ?? null,
                    'display_order' => $i + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
