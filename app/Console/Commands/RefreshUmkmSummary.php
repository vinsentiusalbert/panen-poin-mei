<?php

namespace App\Console\Commands;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi tabel summary_panen_poin_umkm untuk Panen Poin UMKM Area 3.
 *
 * Pemakaian:
 *   php artisan umkm:refresh-summary          -> periode yang sedang berjalan
 *   php artisan umkm:refresh-summary p1       -> paksa Periode 1
 *   php artisan umkm:refresh-summary p2       -> paksa Periode 2
 *
 * Aturan poin sama dengan program utama: floor(settlement / 250.000).
 * Poin dihitung TERPISAH per periode (P2 mulai dari 0).
 */
class RefreshUmkmSummary extends Command
{
    protected $signature = 'umkm:refresh-summary {period? : p1 atau p2}';

    protected $description = 'Hitung ulang summary poin Panen Poin UMKM per periode';

    private const POINT_PER = 250000;

    /** Samakan dengan periods() di PanenPoinUmkmController. */
    private function periods(): array
    {
        return [
            'p1' => [Carbon::create(2026, 11, 13)->startOfDay(), Carbon::create(2026, 12, 6)->endOfDay()],
            'p2' => [Carbon::create(2026, 12, 7)->startOfDay(), Carbon::create(2026, 12, 31)->endOfDay()],
        ];
    }

    public function handle(): int
    {
        $periods = $this->periods();
        $key = $this->argument('period');

        if ($key === null) {
            $today = Carbon::now();
            foreach ($periods as $k => [$s, $e]) {
                if ($today->between($s, $e)) {
                    $key = $k;
                    break;
                }
            }
        }

        if ($key === null || !isset($periods[$key])) {
            $this->warn('Tidak ada periode aktif. Sebutkan manual: p1 atau p2.');
            return self::FAILURE;
        }

        [$startAt, $endAt] = $periods[$key];
        $start = $startAt->toDateString();
        // Jangan hitung melewati hari ini
        $end = Carbon::now()->min($endAt)->toDateString();

        $this->info("Periode {$key}: {$start} s/d {$end}");

        $total = 0;

        DB::transaction(function () use ($key, $start, $end, $startAt, $endAt, &$total) {
            // Hapus hanya baris periode ini, periode lain tetap aman
            DB::table('summary_panen_poin_umkm')->where('period_key', $key)->delete();

            // TODO Area 3: tambahkan filter area di sini jika ada kolomnya
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
                    if ($settlement <= 0) {
                        continue;
                    }

                    $poin = (int) floor($settlement / self::POINT_PER);

                    DB::table('summary_panen_poin_umkm')->insert([
                        'period_key'       => $key,
                        'user_id'          => $canvasser->id,
                        'nama_canvasser'   => $canvasser->name,
                        'email_client'     => $email,
                        'nomor_hp_client'  => $hp,
                        'total_settlement' => $settlement,
                        'poin_bulan_ini'   => $poin,
                        'poin_akumulasi'   => 0,
                        'poin'             => $poin,
                        'periode_label'    => 'Periode ' . substr($key, 1),
                        'period_start'     => $startAt->toDateString(),
                        'period_end'       => $endAt->toDateString(),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                        'source'           => 'umkm',
                    ]);

                    $total++;
                }
            }
        });

        $this->info("Selesai. {$total} baris disimpan.");

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
