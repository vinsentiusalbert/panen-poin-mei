<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

/**
 * Panen Poin UMKM - Area 3 (13 Nov - 31 Des 2026)
 *
 * TAHAP 1: UI saja. Semua data poin, leaderboard dan hadiah di bawah ini
 * adalah DATA CONTOH. Logic poin / redeem akan dihubungkan di tahap berikutnya.
 */
class PanenPoinUmkmController extends Controller
{
    /** Periode program. Ubah tanggal di sini kalau pembagian periode berubah. */
    private function periods(): array
    {
        return [
            [
                'key'   => 'p1',
                'label' => 'Periode 1',
                'start' => Carbon::create(2026, 11, 13)->startOfDay(),
                'end'   => Carbon::create(2026, 12, 6)->endOfDay(),
            ],
            [
                'key'   => 'p2',
                'label' => 'Periode 2',
                'start' => Carbon::create(2026, 12, 7)->startOfDay(),
                'end'   => Carbon::create(2026, 12, 31)->endOfDay(),
            ],
        ];
    }

    /** Selisih hari bertanda (positif jika $to setelah $from), aman untuk Carbon 2 maupun 3. */
    private function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) round(
            ($to->copy()->startOfDay()->timestamp - $from->copy()->startOfDay()->timestamp) / 86400
        );
    }

    private function dummyLeaderboard(string $key): array
    {
        $rows = $key === 'p1'
            ? [
                ['nama_akun' => 'Warung Berkah Jaya',  'email' => 'berkahjaya@example.com',   'canvasser' => 'Andi Pratama', 'poin' => 420],
                ['nama_akun' => 'Toko Sinar Mulia',    'email' => 'sinarmulia@example.com',   'canvasser' => 'Siti Rahma',   'poin' => 385],
                ['nama_akun' => 'Kedai Kopi Senja',    'email' => 'kopisenja@example.com',    'canvasser' => 'Budi Santoso', 'poin' => 340],
                ['nama_akun' => 'Batik Nusantara',     'email' => 'batiknusa@example.com',    'canvasser' => 'Dewi Lestari', 'poin' => 295],
                ['nama_akun' => 'Dapur Bu Ratna',      'email' => 'dapurratna@example.com',   'canvasser' => 'Andi Pratama', 'poin' => 260],
                ['nama_akun' => 'Laundry Bersih Kilat','email' => 'laundrykilat@example.com', 'canvasser' => 'Rizky Maulana','poin' => 215],
                ['nama_akun' => 'Bengkel Maju Motor',  'email' => 'majumotor@example.com',    'canvasser' => 'Siti Rahma',   'poin' => 180],
                ['nama_akun' => 'Snack Renyah Id',     'email' => 'snackrenyah@example.com',  'canvasser' => 'Budi Santoso', 'poin' => 120],
            ]
            : [];

        return $rows;
    }

        private function prizes(string $key): array
        {
            return \App\Models\PrizeV4::orderByDesc('point')->get()
                ->map(fn ($p) => [
                    'name'  => $p->name,
                    'img'   => basename($p->img),   // view sudah menambah 'hadiah/'
                    'point' => $p->point,
                    'stock' => $p->stock,
                ])->all();
        }

    public function index()
    {
        $today = Carbon::today();

        $periods = collect($this->periods())->map(function ($p) use ($today) {
            if ($today->lt($p['start'])) {
                $p['status'] = 'upcoming';
                $p['status_label'] = 'Segera Dimulai';
                $p['days_info'] = 'Mulai ' . $this->daysBetween($today, $p['start']) . ' hari lagi';
            } elseif ($today->gt($p['end'])) {
                $p['status'] = 'ended';
                $p['status_label'] = 'Selesai';
                $p['days_info'] = 'Periode telah berakhir';
            } else {
                $p['status'] = 'active';
                $p['status_label'] = 'Sedang Berlangsung';
                $p['days_info'] = ($this->daysBetween($today, $p['end']) + 1) . ' hari tersisa';
            }

            $p['leaderboard'] = $this->dummyLeaderboard($p['key']);
            $p['prizes'] = $this->prizes($p['key']);

            return $p;
        })->all();

        $activeKey = collect($periods)->firstWhere('status', 'active')['key']
            ?? (collect($periods)->firstWhere('status', 'upcoming')['key'] ?? end($periods)['key']);

        $programStart = $periods[0]['start'];
        $programEnd   = end($periods)['end'];
        $totalDays    = $this->daysBetween($programStart, $programEnd) + 1;
        $elapsedDays  = max(0, min($totalDays, $this->daysBetween($programStart, $today) + 1));

        return view('umkm.index', compact(
            'periods', 'activeKey', 'programStart', 'programEnd', 'totalDays', 'elapsedDays'
        ));
    }
}
