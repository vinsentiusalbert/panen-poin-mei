<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengisi tabel summary_panen_poin_v5 untuk Panen Poin Utama V5 (13 Nov - 6 Des 2026).
 *
 * Pemakaian:
 *   php artisan panenpoin:refresh-v5
 */
class RefreshV5Summary extends Command
{
    protected $signature = 'panenpoin:refresh-v5';

    protected $description = 'Hitung ulang summary poin Panen Poin Utama V5 (13 Nov - 6 Des 2026)';

    private const POINT_PER = 250000;

    public function handle(): int
    {
        $startAt = Carbon::create(2026, 11, 13)->startOfDay();
        $endAt   = Carbon::create(2026, 12, 6)->endOfDay();

        $start = $startAt->toDateString();
        // Jangan hitung melewati hari ini
        $end   = Carbon::now()->min($endAt)->toDateString();

        $this->info("Periode Panen Poin V5: {$start} s/d {$end}");

        $total = 0;
        $summaryTable = 'summary_panen_poin_v5';

        // Simpan poin_redeem dan poin_package yang sudah ada agar tidak hilang saat refresh
        $existingAdjustments = [];
        if (Schema::hasTable($summaryTable)) {
            $existingAdjustments = DB::table($summaryTable)
                ->select('email_client', 'poin_package', 'poin_redeem', 'remark')
                ->get()
                ->keyBy(fn ($row) => strtolower(trim($row->email_client)))
                ->toArray();
        }

        DB::transaction(function () use ($start, $end, $startAt, $endAt, $summaryTable, $existingAdjustments, &$total) {
            DB::table($summaryTable)->delete();

            $canvassers = User::where('role', 'cvsr')->get();

            foreach ($canvassers as $canvasser) {
                $clients = $this->clientsOf($canvasser->id);
                if (empty($clients)) {
                    continue;
                }

                $emails = array_keys($clients);

                $settlements = DB::table('report_balance_top_up')
                    ->select(
                        DB::raw('LOWER(TRIM(email_client)) as email'),
                        DB::raw('SUM(CAST(total_settlement_klien AS DECIMAL(15,2))) as total')
                    )
                    ->whereBetween('tgl_transaksi', [$start, $end])
                    ->whereNotNull('total_settlement_klien')
                    ->whereIn(DB::raw('LOWER(TRIM(email_client))'), $emails)
                    ->groupBy(DB::raw('LOWER(TRIM(email_client))'))
                    ->pluck('total', 'email')
                    ->toArray();

                foreach ($clients as $email => $hp) {
                    $settlement = $settlements[$email] ?? 0;
                    $prev = $existingAdjustments[$email] ?? null;
                    $poinPackage = (int) ($prev->poin_package ?? 0);
                    $poinRedeem  = (int) ($prev->poin_redeem ?? 0);

                    if ($settlement <= 0 && $poinPackage <= 0) {
                        continue;
                    }

                    $poin = (int) floor($settlement / self::POINT_PER);

                    $row = [
                        'user_id'          => $canvasser->id,
                        'nama_canvasser'   => $canvasser->name,
                        'email_client'     => $email,
                        'nomor_hp_client'  => $hp,
                        'total_settlement' => $settlement,
                        'poin_bulan_ini'   => $poin,
                        'poin_akumulasi'   => 0,
                        'poin'             => $poin,
                        'bulan'            => $startAt->copy()->locale('id')->translatedFormat('F Y'),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];

                    if (Schema::hasColumn($summaryTable, 'poin_package')) {
                        $row['poin_package'] = $poinPackage;
                    }
                    if (Schema::hasColumn($summaryTable, 'poin_redeem')) {
                        $row['poin_redeem'] = $poinRedeem;
                    }
                    if (Schema::hasColumn($summaryTable, 'remark') && isset($prev->remark)) {
                        $row['remark'] = $prev->remark;
                    }
                    if (Schema::hasColumn($summaryTable, 'period_start')) {
                        $row['period_start'] = $startAt->toDateString();
                    }
                    if (Schema::hasColumn($summaryTable, 'period_end')) {
                        $row['period_end'] = $endAt->toDateString();
                    }

                    DB::table($summaryTable)->insert($row);
                    $total++;
                }
            }
        });

        $this->info("Selesai. {$total} baris disimpan ke {$summaryTable}.");

        return self::SUCCESS;
    }

    /** @return array<string,string> [email => nomor_hp] milik satu canvasser */
    private function clientsOf(int $canvasserId): array
    {
        $clients = [];

        $table = collect(['user_panen_poin', 'user_panen_poins'])
            ->first(fn ($t) => Schema::hasTable($t));

        if ($table) {
            $panen = DB::table($table)
                ->where('user_id', $canvasserId)
                ->select('akun_myads_pelanggan', 'nomor_hp_pelanggan')
                ->get();

            if ($panen->isNotEmpty()) {
                foreach ($panen as $row) {
                    $clients[strtolower(trim($row->akun_myads_pelanggan))] = $row->nomor_hp_pelanggan;
                }
                return $clients;
            }
        }

        $leads = DB::table('leads_master')
            ->where('user_id', $canvasserId)
            ->select('email', 'mobile_phone')
            ->get();

        foreach ($leads as $lead) {
            $clients[strtolower(trim($lead->email))] = $lead->mobile_phone ?? '-';
        }

        return $clients;
    }
}
