<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\ServicePackageHistory;
use App\Models\ServicePackageItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::pluck('id', 'service_code');

        $packages = [
            [
                'code' => 'PKG001',
                'name' => 'Goi cham soc rang co ban',
                'description' => 'Goi cham soc rang mieng co ban: kham, cao voi, danh bong va tu van.',
                'status' => ServicePackage::STATUS_ACTIVE,
                'visibility' => ServicePackage::VISIBILITY_PUBLIC,
                'package_price' => 500000,
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'usage_validity_days' => 90,
                'conditions' => 'Ap dung mot lan/khach hang trong thoi gian hieu luc.',
                'items' => [
                    ['code' => 'DV0001', 'quantity' => 1],
                    ['code' => 'DV0002', 'quantity' => 1],
                ],
            ],
            [
                'code' => 'PKG002',
                'name' => 'Goi tay trang rang chuyen sau',
                'description' => 'Goi tay trang rang ket hop tu van va theo doi sau dieu tri.',
                'status' => ServicePackage::STATUS_ACTIVE,
                'visibility' => ServicePackage::VISIBILITY_PUBLIC,
                'package_price' => 2400000,
                'effective_from' => '2026-02-01',
                'effective_to' => '2026-12-31',
                'usage_validity_days' => 60,
                'conditions' => 'Khach hang phai hoan tat kham va cao voi truoc khi tay trang.',
                'items' => [
                    ['code' => 'DV0001', 'quantity' => 1],
                    ['code' => 'DV0002', 'quantity' => 1],
                    ['code' => 'DV0007', 'quantity' => 1],
                ],
            ],
            [
                'code' => 'PKG003',
                'name' => 'Goi nieng rang co ban',
                'description' => 'Goi nieng rang mac cai kim loai bao gom cac dich vu kham va cham soc dinh ky.',
                'status' => ServicePackage::STATUS_DRAFT,
                'visibility' => ServicePackage::VISIBILITY_INTERNAL,
                'package_price' => 24500000,
                'effective_from' => '2026-03-01',
                'effective_to' => '2027-02-28',
                'usage_validity_days' => 720,
                'conditions' => 'Goi keo dai 24 thang. Khach hang phai theo dung lich tai kham.',
                'items' => [
                    ['code' => 'DV0001', 'quantity' => 4],
                    ['code' => 'DV0002', 'quantity' => 4],
                    ['code' => 'DV0005', 'quantity' => 1],
                ],
            ],
        ];

        foreach ($packages as $row) {
            $items = collect($row['items'])
                ->filter(fn ($i) => isset($services[$i['code']]))
                ->map(function ($i) use ($services) {
                    return [
                        'service_id' => $services[$i['code']],
                        'quantity' => $i['quantity'],
                    ];
                })
                ->values()
                ->all();
            if (empty($items)) {
                continue;
            }

            $package = ServicePackage::firstOrNew(['code' => $row['code']]);

            $package->fill([
                'code' => $row['code'],
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'description' => $row['description'],
                'status' => $row['status'],
                'visibility' => $row['visibility'],
                'package_price' => $row['package_price'],
                'effective_from' => $row['effective_from'],
                'effective_to' => $row['effective_to'],
                'usage_validity_days' => $row['usage_validity_days'],
                'conditions' => $row['conditions'],
                'version_number' => 1,
            ]);

            $serviceModels = Service::whereIn('id', array_column($items, 'service_id'))->get()->keyBy('id');
            $original = 0.0;
            foreach ($items as $i) {
                $svc = $serviceModels->get($i['service_id']);
                $original += ($svc ? (float) $svc->price : 0) * $i['quantity'];
            }
            $original = round($original, 2);
            $packagePrice = (float) $package->package_price;
            $package->original_price = $original;
            $package->discount_amount = max(0, $original - $packagePrice);
            $package->discount_percent = $original > 0 ? round((($original - $packagePrice) / $original) * 100, 2) : 0;
            $package->save();

            ServicePackageItem::where('package_id', $package->id)->delete();
            foreach ($items as $idx => $i) {
                $svc = $serviceModels->get($i['service_id']);
                ServicePackageItem::create([
                    'package_id' => $package->id,
                    'service_id' => $i['service_id'],
                    'quantity' => $i['quantity'],
                    'unit_price' => $svc ? (float) $svc->price : 0,
                    'display_order' => $idx,
                ]);
            }

            ServicePackageHistory::firstOrCreate(
                [
                    'package_id' => $package->id,
                    'action' => 'created',
                ],
                [
                    'payload' => [
                        'name' => $package->name,
                        'status' => $package->status,
                        'item_count' => count($items),
                    ],
                    'reason' => 'Seed du lieu mau',
                    'created_at' => Carbon::now(),
                ]
            );
        }
    }
}
