<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('service_prices')) {
            return;
        }

        $services = DB::table('services')->select(['id', 'price', 'created_at', 'created_by'])->get();
        $now = now();

        foreach ($services as $svc) {
            $existing = DB::table('service_prices')->where('service_id', $svc->id)->count();
            if ($existing > 0) {
                continue;
            }

            $history = DB::table('service_price_history')
                ->where('service_id', $svc->id)
                ->orderBy('effective_date')
                ->orderBy('id')
                ->get();

            if ($history->isEmpty()) {
                if ((float) $svc->price > 0) {
                    DB::table('service_prices')->insert([
                        'service_id' => $svc->id,
                        'price' => $svc->price,
                        'currency_code' => 'VND',
                        'is_tax_inclusive' => true,
                        'effective_from' => $svc->created_at ?? $now,
                        'effective_to' => null,
                        'status' => 'active',
                        'proposal_status' => 'approved',
                        'reason' => 'Initial price (migration)',
                        'created_by' => $svc->created_by,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }

            $rows = $history->values()->all();
            $count = count($rows);
            foreach ($rows as $i => $h) {
                $from = $h->effective_date ?? $h->created_at ?? $now;
                $to = $i + 1 < $count ? ($rows[$i + 1]->effective_date ?? null) : null;
                $isLast = $i + 1 === $count;

                DB::table('service_prices')->insert([
                    'service_id' => $svc->id,
                    'price' => $h->new_price,
                    'currency_code' => 'VND',
                    'is_tax_inclusive' => true,
                    'effective_from' => $from,
                    'effective_to' => $to,
                    'status' => $isLast ? 'active' : 'expired',
                    'proposal_status' => 'approved',
                    'reason' => $h->reason ?? 'Migrated from history',
                    'created_by' => $h->changed_by,
                    'created_at' => $h->created_at ?? $now,
                    'updated_at' => $h->created_at ?? $now,
                ]);
            }

            // Sync cache services.price to current active price
            $latest = DB::table('service_prices')
                ->where('service_id', $svc->id)
                ->where('status', 'active')
                ->orderByDesc('effective_from')
                ->first();
            if ($latest && (float) $latest->price !== (float) $svc->price) {
                DB::table('services')->where('id', $svc->id)->update(['price' => $latest->price]);
            }
        }
    }

    public function down(): void
    {
        // Backfill is forward-only; no down migration.
    }
};
