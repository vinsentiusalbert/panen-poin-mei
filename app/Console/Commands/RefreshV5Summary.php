<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengisi tabel summary_panen_poin_v5 untuk Panen Poin Utama V5
 * Periode 1 (13 Nov - 6 Des 2026) & Periode 2 (7 Des - 31 Des 2026).
 *
 * Pemakaian:
 *   php artisan panenpoin:refresh-v5
 *   php artisan panenpoin:refresh-v5 --period=p1
 *   php artisan panenpoin:refresh-v5 --period=p2
 */
class RefreshV5Summary extends Command
{
    protected $signature = 'panenpoin:refresh-v5 {--period= : Periode target (p1 atau p2)}';

    protected $description = 'Hitung ulang summary poin Panen Poin Utama V5 per periode';

    private const POINT_PER = 250000;

    public function handle(): int
    {
        $periodOption = $this->option('period');
        $periodKey = in_array($periodOption, ['p1', 'p2'], true)
            ? $periodOption
            : (Carbon::today()->gte(Carbon::create(2026, 12, 7)) ? 'p2' : 'p1');

        if ($periodKey === 'p2') {
            $startAt = Carbon::create(2026, 12, 7)->startOfDay();
            $endAt   = Carbon::create(2026, 12, 31)->endOfDay();
            $periodeLabel = '7 Des - 31 Des 2026';
        } else {
            $startAt = Carbon::create(2026, 11, 13)->startOfDay();
            $endAt   = Carbon::create(2026, 12, 6)->endOfDay();
            $periodeLabel = '13 Nov - 6 Des 2026';
        }

        // Jangan hitung melewati hari ini
        $endBound = Carbon::now()->min($endAt);

        $this->info("Periode Panen Poin V5 [{$periodKey}]: {$startAt->toDateTimeString()} s/d {$endBound->toDateTimeString()}");

        $total = 0;
        $summaryTable = 'summary_panen_poin_v5';
        $hasPeriodKey = Schema::hasColumn($summaryTable, 'period_key');

        // Simpan poin_redeem dan poin_package yang sudah ada pada periode ini agar tidak hilang saat refresh
        $existingAdjustments = [];
        if (Schema::hasTable($summaryTable)) {
            $adjQuery = DB::table($summaryTable)
                ->select('email_client', 'poin_package', 'poin_redeem', 'remark');

            if ($hasPeriodKey) {
                $adjQuery->where('period_key', $periodKey);
            }

            $existingAdjustments = $adjQuery
                ->get()
                ->keyBy(fn ($row) => strtolower(trim($row->email_client)))
                ->toArray();
        }

        // Daftar email mitra_sbp untuk dikecualikan jika tabel ada
        $mitraEmails = [];
        if (Schema::hasTable('mitra_sbp')) {
            $mitraEmails = DB::table('mitra_sbp')
                ->whereNotNull('email_myads')
                ->pluck('email_myads')
                ->map(fn ($e) => strtolower(trim($e)))
                ->flip()
                ->toArray();
        }

        DB::transaction(function () use ($startAt, $endAt, $endBound, $periodKey, $periodeLabel, $summaryTable, $hasPeriodKey, $existingAdjustments, $mitraEmails, &$total) {
            if ($hasPeriodKey) {
                DB::table($summaryTable)->where('period_key', $periodKey)->delete();
            } else {
                DB::table($summaryTable)->delete();
            }

            $canvassers = User::where('role', 'cvsr')->get();

            foreach ($canvassers as $canvasser) {
                $clients = $this->clientsOf($canvasser->id);
                if (empty($clients)) {
                    continue;
                }

                // Kecualikan mitra_sbp
                $clients = array_filter(
                    $clients,
                    fn ($hp, $email) => !isset($mitraEmails[$email]),
                    ARRAY_FILTER_USE_BOTH
                );

                if (empty($clients)) {
                    continue;
                }

                $emails = array_keys($clients);

                $settlements = DB::table('report_balance_top_up')
                    ->select(
                        DB::raw('LOWER(TRIM(email_client)) as email'),
                        DB::raw('SUM(CAST(total_settlement_klien AS DECIMAL(15,2))) as total')
                    )
                    ->whereBetween('tgl_transaksi', [$startAt->toDateTimeString(), $endBound->toDateTimeString()])
                    ->whereNotNull('total_settlement_klien')
                    ->whereIn(DB::raw('LOWER(TRIM(email_client))'), $emails)
                    ->groupBy(DB::raw('LOWER(TRIM(email_client))'))
                    ->pluck('total', 'email')
                    ->toArray();

                $packagePoints = $this->packagePointsOf($startAt, $endBound, $emails);

                foreach ($clients as $email => $hp) {
                    $settlement = (float) ($settlements[$email] ?? 0);
                    $prev = $existingAdjustments[$email] ?? null;
                    $autoPkg = (int) ($packagePoints[$email] ?? 0);
                    $prevPkg = (int) ($prev->poin_package ?? 0);
                    $poinPackage = max($autoPkg, $prevPkg);
                    $poinRedeem  = (int) ($prev->poin_redeem ?? 0);

                    if ($settlement <= 0 && $poinPackage <= 0) {
                        continue;
                    }

                    $poin = (int) floor($settlement / self::POINT_PER);
                    $totalPoinLiga = $poin + $poinPackage;
                    $remark = $this->resolveLeagueRemark($totalPoinLiga);

                    $row = [
                        'user_id'          => $canvasser->id,
                        'nama_canvasser'   => $canvasser->name,
                        'email_client'     => $email,
                        'nomor_hp_client'  => $hp,
                        'total_settlement' => $settlement,
                        'poin_bulan_ini'   => $poin,
                        'poin_akumulasi'   => 0,
                        'poin'             => $poin,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];

                    if ($hasPeriodKey) {
                        $row['period_key'] = $periodKey;
                    }
                    if (Schema::hasColumn($summaryTable, 'bulan')) {
                        $row['bulan'] = $startAt->copy()->locale('id')->translatedFormat('F Y');
                    }
                    if (Schema::hasColumn($summaryTable, 'poin_package')) {
                        $row['poin_package'] = $poinPackage;
                    }
                    if (Schema::hasColumn($summaryTable, 'poin_redeem')) {
                        $row['poin_redeem'] = $poinRedeem;
                    }
                    if (Schema::hasColumn($summaryTable, 'remark')) {
                        $row['remark'] = $remark;
                    }
                    if (Schema::hasColumn($summaryTable, 'period_start')) {
                        $row['period_start'] = $startAt->toDateString();
                    }
                    if (Schema::hasColumn($summaryTable, 'periode_label')) {
                        $row['periode_label'] = $periodeLabel;
                    }
                    if (Schema::hasColumn($summaryTable, 'period_end')) {
                        $row['period_end'] = $endAt->toDateString();
                    }

                    DB::table($summaryTable)->insert($row);
                    $total++;
                }
            }
        });

        $this->info("Selesai. {$total} baris disimpan ke {$summaryTable} ({$periodKey}).");

        return self::SUCCESS;
    }

    private function resolveLeagueRemark(int $totalPoin): ?string
    {
        if ($totalPoin >= 450) {
            return 'Champion';
        }
        if ($totalPoin >= 100) {
            return 'Rising Star';
        }
        if ($totalPoin >= 1) {
            return 'Rookie';
        }

        return null;
    }

    /** @return array<string,string> [email => nomor_hp] gabungan user_panen_poin & leads_master */
    private function clientsOf(int $canvasserId): array
    {
        $clients = [];

        $leads = DB::table('leads_master')
            ->where('user_id', $canvasserId)
            ->whereNotNull('email')
            ->select('email', 'mobile_phone')
            ->get();

        foreach ($leads as $lead) {
            $email = strtolower(trim($lead->email));
            if ($email !== '') {
                $clients[$email] = $lead->mobile_phone ?? '-';
            }
        }

        $table = collect(['user_panen_poin', 'user_panen_poins'])
            ->first(fn ($t) => Schema::hasTable($t));

        if ($table) {
            $panen = DB::table($table)
                ->where('user_id', $canvasserId)
                ->whereNotNull('akun_myads_pelanggan')
                ->select('akun_myads_pelanggan', 'nomor_hp_pelanggan')
                ->get();

            foreach ($panen as $row) {
                $email = strtolower(trim($row->akun_myads_pelanggan));
                if ($email !== '') {
                    $clients[$email] = $row->nomor_hp_pelanggan ?: ($clients[$email] ?? '-');
                }
            }
        }

        return $clients;
    }

    /** @return array<string,int> [email => total_poin_paket] pada rentang periode */
    private function packagePointsOf(Carbon $startAt, Carbon $endBound, array $emails): array
    {
        if (empty($emails) || !Schema::hasTable('data_paket_seasonal')) {
            return [];
        }

        // Daftar nama paket promo V5 dan nilai poinnya (case-insensitive)
        $v5PackagePoints = [
            'SMS BROADCAST 5JT' => 20,
        ];

        $rows = DB::table('data_paket_seasonal')
            ->select(
                DB::raw('LOWER(TRIM(email)) as email'),
                DB::raw('UPPER(TRIM(name)) as pkg_name'),
                DB::raw('COUNT(*) as qty')
            )
            ->whereBetween('created_at', [$startAt->toDateTimeString(), $endBound->toDateTimeString()])
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails)
            ->whereIn(DB::raw('UPPER(TRIM(name))'), array_keys($v5PackagePoints))
            ->groupBy(DB::raw('LOWER(TRIM(email))'), DB::raw('UPPER(TRIM(name))'))
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $pts = $v5PackagePoints[$r->pkg_name] ?? 0;
            $result[$r->email] = ($result[$r->email] ?? 0) + ($pts * (int) $r->qty);
        }

        return $result;
    }
}