<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserPanenPoin;
use App\Models\User;
use App\Models\PrizeV4;
use App\Models\PrizeRedeemV4;
use App\Models\UserContactInfoV4;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PanenPoinController extends Controller
{
    private function emptyLeagueData(): array
    {
        return [
            'poin_under_100' => [],
            'poin_101_300' => [],
            'poin_over_301' => [],
        ];
    }

    private function programStartDate(): Carbon
    {
        return Carbon::create(2026, 9, 1)->startOfDay();
    }

    private function programEndDate(): Carbon
    {
        return Carbon::create(2026, 10, 9)->endOfDay();
    }

    private function redeemStartDate(): Carbon
    {
        return Carbon::create(2026, 9, 1)->startOfDay();
    }

    private function redeemEndDate(): Carbon
    {
        return Carbon::create(2026, 10, 9)->endOfDay();
    }

    private function activeProgramMonthDate(): Carbon
    {
        $today = Carbon::today();

        if ($today->lt($this->programStartDate())) {
            return $this->programStartDate()->copy();
        }

        if ($today->gt($this->programEndDate())) {
            return $this->programEndDate()->copy();
        }

        return $today;
    }

    private function summaryReferenceDate(): Carbon
    {
        if (
            Schema::hasColumn($this->summaryPanenPoinTable(), 'period_end')
            && DB::table($this->summaryPanenPoinTable())->whereNotNull('period_end')->exists()
        ) {
            $latestPeriodEnd = DB::table($this->summaryPanenPoinTable())
                ->whereDate('period_end', '<=', $this->programEndDate()->toDateString())
                ->max('period_end');

            if ($latestPeriodEnd) {
                return Carbon::parse($latestPeriodEnd)->endOfDay();
            }
        }

        $latestCreatedAt = DB::table($this->summaryPanenPoinTable())
            ->whereDate('created_at', '<=', $this->programEndDate()->toDateString())
            ->max('created_at');

        if ($latestCreatedAt) {
            return Carbon::parse($latestCreatedAt);
        }

        return $this->activeProgramMonthDate();
    }

    private function akunPanenPoinTable(): string
    {
        return 'akun_panen_poin_v4';
    }

    private function summaryPanenPoinTable(): string
    {
        return 'summary_panen_poin_v4';
    }

    private function prizesTable(): string
    {
        return (new PrizeV4())->getTable();
    }

    private function prizeRedeemsTableV4(): string
    {
        return (new PrizeRedeemV4())->getTable();
    }

    private function userContactInfosTable(): string
    {
        return (new UserContactInfoV4())->getTable();
    }

    private function relatedRedeemUserIds($user): array
    {
        if (!$user) {
            return [];
        }

        $email = strtolower($user->email_client ?? $user->email ?? '');
        $userIds = [$user->id];

        if ($email !== '') {
            foreach ([$this->akunPanenPoinTable()] as $table) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'email_client')) {
                    continue;
                }

                $matchedIds = DB::table($table)
                    ->whereRaw('LOWER(email_client) = ?', [$email])
                    ->pluck('id')
                    ->all();

                $userIds = array_merge($userIds, $matchedIds);
            }
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    private function optionalColumn(string $table, string $column, string $fallback = 'NULL'): string
    {
        if (Schema::hasColumn($table, $column)) {
            return $column;
        }

        return "{$fallback} as {$column}";
    }

    private function applySummaryReferenceFilter($query, Carbon $referenceDate, ?string $alias = null)
    {
        $table = $this->summaryPanenPoinTable();
        $prefix = $alias ? "{$alias}." : '';

        if (Schema::hasColumn($table, 'period_start') && Schema::hasColumn($table, 'period_end')) {
            return $query
                ->whereNotNull("{$prefix}period_start")
                ->whereNotNull("{$prefix}period_end")
                ->whereDate("{$prefix}period_start", '<=', $referenceDate->toDateString())
                ->whereDate("{$prefix}period_end", '>=', $referenceDate->toDateString());
        }

        return $query
            ->whereMonth("{$prefix}created_at", $referenceDate->month)
            ->whereYear("{$prefix}created_at", $referenceDate->year);
    }

    private function prizeRedeemsUnionQuery()
    {
        return DB::table($this->prizeRedeemsTableV4())
            ->selectRaw($this->prizeRedeemSelectRaw($this->prizeRedeemsTableV4(), 'v4'));
    }

    private function prizeRedeemsQuery(string $alias = 'pr')
    {
        return DB::query()->fromSub($this->prizeRedeemsUnionQuery(), $alias);
    }

    private function akunPanenPoinUnionQuery()
    {
        return DB::table($this->akunPanenPoinTable())
            ->selectRaw("'v4' as source_version, id, nama_akun, email_client");
    }

    private function akunPanenPoinQuery(string $alias = 'u')
    {
        return DB::query()->fromSub($this->akunPanenPoinUnionQuery(), $alias);
    }

    private function userContactInfosUnionQuery()
    {
        return DB::table($this->userContactInfosTable())
            ->selectRaw("'v4' as source_version, user_id, phone, address, remark, created_at");
    }

    private function latestUserContactInfosQuery(string $alias = 'uc')
    {
        $base = DB::query()->fromSub($this->userContactInfosUnionQuery(), 'uci');

        return DB::query()->fromSub(
            $base->select(
                'uci.source_version',
                'uci.user_id',
                'uci.phone',
                'uci.address',
                'uci.remark',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY uci.source_version, uci.user_id ORDER BY uci.created_at DESC) as row_num')
            ),
            $alias
        );
    }

    private function resolvePrizeRedeemTable(string $sourceVersion): string
    {
        return $this->prizeRedeemsTableV4();
    }

    private function prizeRedeemSelectRaw(string $table, string $sourceVersion): string
    {
        return implode(', ', [
            "'{$sourceVersion}' as source_version",
            'id',
            'user_id',
            'prize_id',
            $this->optionalPrizeRedeemColumn($table, 'point_used', '0'),
            'created_at',
            'updated_at',
            $this->optionalPrizeRedeemColumn($table, 'shipped_at'),
            $this->optionalPrizeRedeemColumn($table, 'shipping_proof_path'),
            $this->optionalPrizeRedeemColumn($table, 'proof_path'),
        ]);
    }

    private function optionalPrizeRedeemColumn(string $table, string $column, string $fallback = 'NULL'): string
    {
        return $this->optionalColumn($table, $column, $fallback);
    }

    private function ensureAdminRedeemAccess()
    {
        $user = auth()->user();
        $email = $user ? strtolower($user->email_client ?? $user->email ?? '') : '';
        if ($email !== 'arifasep@gmail.com') {
            abort(403);
        }
        // dd($email);
        return $user;
    }
    // Tampilkan halaman input data
    public function index()
    {
        // logUserLogin();
        return view('panenpoin.inputdatapoin');
    }
    
    // Simpan data panen poin
    public function store(Request $request)
    {
        $request->validate([
            'nama_pelanggan' => 'required|string|max:255',
            'akun_myads_pelanggan' => 'required|max:255',
            'nomor_hp_pelanggan' => 'required|string|max:20',
        ]);
        
        try {
            UserPanenPoin::create([
                'user_id' => Auth::id(),
                'nama_pelanggan' => $request->nama_pelanggan,
                'akun_myads_pelanggan' => strtolower($request->akun_myads_pelanggan),
                'nomor_hp_pelanggan' => $request->nomor_hp_pelanggan,
            ]);
            
            return redirect()->route('home')
                ->with('success', 'Data pelanggan berhasil disimpan!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menyimpan data: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    // Tampilkan halaman report
    public function report()
    {
        logUserLogin();
        $months = [];

        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->format('Y-m-01'); // bulan sekarang, tanggal 01

        for ($i = 1; $i <= 12; ++$i) {
            $date = Carbon::create($currentYear, $i, 1);
            $months[] = [
                'value' => $date->format('Y-m-d'), // e.g., 2025-05-01
                'label' => $date->translatedFormat('F Y'), // e.g., Mei 2025
                'selected' => $date->format('Y-m-d') === $currentMonth,
            ];
        }
        return view('panenpoin.reportpoin', compact('months'));
    }
    
    // Get data untuk DataTable
    public function getReportData(Request $request)
    {  
        \Log::info('=== GET REPORT DATA CALLED ===');
        // \Log::info('User: ' . Auth::user()->name);
        \Log::info('Request URI: ' . $request->getRequestUri());
        \Log::info('Filter Tanggal: ' . $request->tanggal);
        
        try {
            $user = auth()->user();
            $relatedRedeemUserIds = $this->relatedRedeemUserIds($user);
            \Log::info('Starting calculatePanenPoinData...');
            $data = $this->calculatePanenPoinData($request->tanggal);
            $prizes = PrizeV4::orderBy('point', 'desc')->get();
            if ($user) {
                $date = $this->summaryReferenceDate();
                $pointQuery = DB::table($this->summaryPanenPoinTable())
                    ->select(
                        'nama_canvasser',
                        'email_client',
                        'nomor_hp_client',
                        DB::raw('CAST(total_settlement AS DECIMAL(15,2)) as total_settlement_raw'),
                        DB::raw('FORMAT(total_settlement, 0, "id_ID") as total_settlement'),
                        'poin_bulan_ini',
                        'poin_akumulasi',
                        DB::raw('((COALESCE(poin, 0) + COALESCE(poin_package, 0)) - COALESCE(poin_redeem, 0)) as poin'),
                        DB::raw($this->optionalColumn($this->summaryPanenPoinTable(), 'bulan'))
                    )
                    ->where('email_client', '=', Auth::user()->email_client);

                $point = $this->applySummaryReferenceFilter($pointQuery, $date)->first();
            } else {
                $point = 0;
            }
            $date = $this->summaryReferenceDate();
            $redeemCounts = $this->prizeRedeemsQuery()
                ->select('prize_id', DB::raw('COUNT(*) as total'))
                ->when(!empty($relatedRedeemUserIds), fn ($query) => $query->whereIn('user_id', $relatedRedeemUserIds), fn ($query) => $query->whereRaw('1 = 0'))
                ->whereBetween('created_at', [$this->redeemStartDate(), $this->redeemEndDate()])
                ->groupBy('prize_id')
                ->pluck('total', 'prize_id')
                ->toArray();
            $totalRedeemThisPeriod = $this->prizeRedeemsQuery()
                ->when(!empty($relatedRedeemUserIds), fn ($query) => $query->whereIn('user_id', $relatedRedeemUserIds), fn ($query) => $query->whereRaw('1 = 0'))
                ->whereBetween('created_at', [$this->redeemStartDate(), $this->redeemEndDate()])
                ->count();
            $userContactInfos = $user
                ? DB::table($this->userContactInfosTable())
                    ->where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->get()
                : collect();
            $redeemPeriodLimit = 2;
            $today = Carbon::today();
            $redeemStartDate = $this->redeemStartDate();
            $redeemEndDate = $this->redeemEndDate();

            // true kalau hari ini masih dalam periode redeem
            $isRedeemPeriod = $today->between($redeemStartDate, $redeemEndDate);
            $isRedeemEnded = $today->gt($redeemEndDate);
            $userRedeemHistory = $user
                ? $this->prizeRedeemsQuery('pr')
                    ->leftJoin($this->prizesTable() . ' as p', 'p.id', '=', 'pr.prize_id')
                    ->select(
                        'pr.source_version',
                        'pr.id',
                        'pr.created_at',
                        'pr.shipped_at',
                        'pr.shipping_proof_path',
                        'pr.proof_path',
                        'p.name as prize_name',
                        'p.img as prize_image',
                        'p.point as prize_point'
                    )
                    ->when(!empty($relatedRedeemUserIds), fn ($query) => $query->whereIn('pr.user_id', $relatedRedeemUserIds), fn ($query) => $query->whereRaw('1 = 0'))
                    ->orderByDesc('pr.created_at')
                    ->get()
                : collect();
            return view('reward.index_v4', compact(
                'data',
                'point',
                'prizes',
                'redeemCounts',
                'totalRedeemThisPeriod',
                'redeemPeriodLimit',
                'isRedeemPeriod',
                'isRedeemEnded',
                'redeemStartDate',
                'redeemEndDate',
                'userContactInfos',
                'userRedeemHistory',
            ));
                
        } catch (\Exception $e) {
            \Log::error("Error in getReportData: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    // Hitung data panen poin (ambil dari summary table)
    private function calculatePanenPoinData($tanggal = null)
    {
        try {
            \Log::info("=== READING FROM SUMMARY TABLE ===");

            $baseQuery = DB::table($this->summaryPanenPoinTable() . ' as s')
                ->join($this->akunPanenPoinTable() . ' as u', 'u.email_client', '=', 's.email_client')
                ->leftJoin('mitra_sbp', 's.email_client', '=', 'mitra_sbp.email_myads')
                // Exclude email yang ada di mitra_sbp
                ->whereNull('mitra_sbp.id')
                ->select(
                    's.nama_canvasser',
                    's.email_client',
                    's.nomor_hp_client',
                    DB::raw('CAST(s.total_settlement AS DECIMAL(15,2)) as total_settlement_raw'),
                    's.poin_bulan_ini',
                    's.poin_akumulasi',
                    's.poin',
                    's.poin_package',
                    DB::raw('(s.poin + s.poin_package) as total_poin'),
                    DB::raw($this->optionalColumn($this->summaryPanenPoinTable(), 'bulan', "''")),
                    'u.uuid',
                    'u.nama_akun',
                    // 'u.akun_myads_pelanggan',
                    // 'u.nomor_hp_pelanggan',
                    // 'u.nama_pelanggan'
                );

            // Filter bulan
            // if ($tanggal) {
                $date = $this->summaryReferenceDate();
                // $baseQuery->whereMonth('s.created_at', $date->month)
                //         ->whereYear('s.created_at', $date->year);
                
                $this->applySummaryReferenceFilter($baseQuery, $date, 's');
            // }
            // Helper mapper
            $mapResult = function ($query) {
                return $query->orderBy(DB::raw('(s.poin + s.poin_package)'), 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'nama_canvasser' => $item->nama_canvasser,
                            'email_client' => $item->email_client,
                            'nomor_hp_client' => $item->nomor_hp_client,
                            'total_settlement' => number_format($item->total_settlement_raw, 0, ',', '.'),
                            'total_settlement_raw' => $item->total_settlement_raw,
                            'poin_bulan_ini' => $item->poin_bulan_ini,
                            'poin_akumulasi' => $item->poin_akumulasi,
                            'poin' => $item->total_poin,
                            'bulan' => $item->bulan,
                            'uuid' => $item->uuid,
                            'nama_akun' => $item->nama_akun,
                            // 'akun_myads_pelanggan' => $item->akun_myads_pelanggan,
                            // 'nomor_hp_pelanggan' => $item->nomor_hp_pelanggan,
                            // 'nama_pelanggan' => $item->nama_pelanggan,
                        ];
                    })
                    ->toArray();
            };

            $result = [
                'poin_under_100' => $mapResult(
                    (clone $baseQuery)->whereBetween(DB::raw('(s.poin + s.poin_package)'), [0, 100])
                ),
                'poin_101_300' => $mapResult(
                    (clone $baseQuery)->whereBetween(DB::raw('(s.poin + s.poin_package)'), [101, 300])
                ),
                'poin_over_301' => $mapResult(
                    (clone $baseQuery)->where(DB::raw('(s.poin + s.poin_package)'), '>=', 301)
                ),
            ];

            \Log::info("Top 10 results generated per poin range");

            return array_merge($this->emptyLeagueData(), $result);

        } catch (\Exception $e) {
            \Log::error("Error in calculatePanenPoinData: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->emptyLeagueData();
        }
    }

    
    // Export ke Excel
    public function export(Request $request)
    {
        try {
            $data = $this->calculatePanenPoinData($request->tanggal);
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Header
            $monthYear = Carbon::now()->locale('id')->translatedFormat('F Y');
            $sheet->setCellValue('A1', 'LAPORAN PANEN POIN - ' . strtoupper($monthYear));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            // Column headers
            $sheet->setCellValue('A3', 'No');
            $sheet->setCellValue('B3', 'Nama Canvasser');
            $sheet->setCellValue('C3', 'Email Client');
            $sheet->setCellValue('D3', 'Nomor HP Client');
            $sheet->setCellValue('E3', 'Total Settlement');
            $sheet->setCellValue('F3', 'Poin');
            
            $sheet->getStyle('A3:F3')->getFont()->setBold(true);
            $sheet->getStyle('A3:F3')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD9D9D9');
            
            // Data
            $row = 4;
            $no = 1;
            foreach ($data as $item) {
                $sheet->setCellValue('A' . $row, $no++);
                $sheet->setCellValue('B' . $row, $item['nama_canvasser']);
                $sheet->setCellValue('C' . $row, $item['email_client']);
                $sheet->setCellValue('D' . $row, $item['nomor_hp_client']);
                $sheet->setCellValue('E' . $row, $item['total_settlement']);
                $sheet->setCellValue('F' . $row, $item['poin']);
                $row++;
            }
            
            // Auto width
            foreach (range('A', 'F') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Download
            $fileName = 'Laporan_Panen_Poin_' . $monthYear . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (\Exception $e) {
            \Log::error("Error in export: " . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal export data: ' . $e->getMessage());
        }
    }

    public function adminRedeems()
    {
        $this->ensureAdminRedeemAccess();

        $redeems = $this->prizeRedeemsQuery('pr')
            ->leftJoin($this->prizesTable() . ' as p', 'p.id', '=', 'pr.prize_id')
            ->leftJoinSub($this->akunPanenPoinQuery('u'), 'u', function ($join) {
                $join->on('u.id', '=', 'pr.user_id')
                    ->on('u.source_version', '=', 'pr.source_version');
            })
            ->leftJoinSub($this->latestUserContactInfosQuery('uc'), 'uc', function ($join) {
                $join->on('uc.user_id', '=', 'pr.user_id')
                    ->on('uc.source_version', '=', 'pr.source_version')
                    ->where('uc.row_num', '=', 1);
            })
            ->select(
                'pr.source_version',
                'pr.id',
                'u.nama_akun',
                'u.email_client',
                'uc.phone',
                'uc.address',
                'uc.remark',
                'p.name as prize_name',
                'pr.created_at',
                'pr.shipped_at',
                'pr.shipping_proof_path',
                'pr.proof_path'
            )
            ->orderByDesc('pr.created_at')
            ->get();

        return view('reward.admin_redeems_v4', compact('redeems'));
    }

    public function markRedeemShipped(Request $request, $id)
    {
        $this->ensureAdminRedeemAccess();

        $request->validate([
            'source_version' => 'required|in:v4',
            'shipping_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('shipping_proof')->store('redeem_shipping_proofs', 'public');

        DB::table($this->resolvePrizeRedeemTable($request->source_version))
            ->where('id', $id)
            ->update([
                'shipped_at' => now(),
                'shipping_proof_path' => $path,
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Status dikirim dan bukti kirim berhasil disimpan.');
    }

    public function uploadRedeemProof(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->back()->with('error', 'Silakan login terlebih dahulu.');
        }

        $request->validate([
            'redeem_id' => 'required|integer',
            'source_version' => 'required|in:v4',
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $targetRedeem = DB::table($this->resolvePrizeRedeemTable($request->source_version))
            ->where('user_id', $user->id)
            ->where('id', $request->redeem_id)
            ->first();

        if (!$targetRedeem) {
            return redirect()->back()->with('error', 'Belum ada data redeem.');
        }

        $path = $request->file('proof')->store('redeem_proofs', 'public');

        DB::table($this->resolvePrizeRedeemTable($request->source_version))
            ->where('id', $targetRedeem->id)
            ->update([
                'proof_path' => $path,
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Bukti terima berhasil diupload.');
    }

    public function exportRedeemsExcel()
    {
        $this->ensureAdminRedeemAccess();

        $rows = $this->prizeRedeemsQuery('pr')
            ->leftJoin($this->prizesTable() . ' as p', 'p.id', '=', 'pr.prize_id')
            ->leftJoinSub($this->akunPanenPoinQuery('u'), 'u', function ($join) {
                $join->on('u.id', '=', 'pr.user_id')
                    ->on('u.source_version', '=', 'pr.source_version');
            })
            ->leftJoinSub($this->latestUserContactInfosQuery('uc'), 'uc', function ($join) {
                $join->on('uc.user_id', '=', 'pr.user_id')
                    ->on('uc.source_version', '=', 'pr.source_version')
                    ->where('uc.row_num', '=', 1);
            })
            ->select(
                'u.nama_akun',
                'u.email_client',
                'uc.phone',
                'uc.address',
                'uc.remark',
                'p.name as prize_name',
                'pr.created_at',
                'pr.shipped_at',
                'pr.proof_path'
            )
            ->orderByDesc('pr.created_at')
            ->get();
        $fileName = 'Laporan_Redeem_' . Carbon::now()->format('Ymd_His') . '.xls';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        echo '<table border="1">';
        echo '<tr>';
        echo '<th>No</th>';
        echo '<th>Nama Akun</th>';
        echo '<th>Email</th>';
        echo '<th>Nomor Telp</th>';
        echo '<th>Alamat</th>';
        echo '<th>Remark</th>';
        echo '<th>Hadiah</th>';
        echo '<th>Tanggal Redeem</th>';
        echo '<th>Status Kirim</th>';
        echo '<th>Status Bukti Terima</th>';
        echo '</tr>';

        $no = 1;
        foreach ($rows as $item) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . htmlspecialchars($item->nama_akun ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($item->email_client ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($item->phone ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($item->address ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($item->remark ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($item->prize_name ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . Carbon::parse($item->created_at)->format('d-m-Y') . '</td>';
            echo '<td>' . ($item->shipped_at ? 'Sudah Terkirim' : 'Belum Terkirim') . '</td>';
            echo '<td>' . ($item->proof_path ? 'Sudah Upload' : 'Belum Upload') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        exit;
    }
    
    // Refresh Summary Panen Poin (untuk di-schedule)
    public function refreshSummaryPanenPoin()
    {
        try {
            \Log::info('=== REFRESH SUMMARY PANEN POIN STARTED ===');

            $referenceDate = $this->activeProgramMonthDate();
            $startDate = $referenceDate->copy()->startOfMonth()->format('Y-m-d');
            $endDate = $referenceDate->copy()->endOfMonth()->min($this->programEndDate())->format('Y-m-d');
            
            // Ambil semua canvasser
            $canvassers = User::where('role', 'cvsr')->get();
            
            $totalProcessed = 0;
            
            // Hapus data summary bulan ini dulu
            DB::table($this->summaryPanenPoinTable())->truncate();
            
            foreach ($canvassers as $canvasser) {
                // Ambil email dari user_panen_poin yang diinput oleh canvasser ini
                $panenPoinData = UserPanenPoin::where('user_id', $canvasser->id)
                    ->select('akun_myads_pelanggan', 'nomor_hp_pelanggan')
                    ->get();
                
                $clientEmails = [];
                
                if ($panenPoinData->isNotEmpty()) {
                    foreach ($panenPoinData as $data) {
                        $clientEmails[] = [
                            'email' => strtolower(trim($data->akun_myads_pelanggan)),
                            'nomor_hp' => $data->nomor_hp_pelanggan
                        ];
                    }
                } else {
                    $leadsData = DB::table('leads_master')
                        ->where('user_id', $canvasser->id)
                        ->select('email', 'mobile_phone')
                        ->get();
                    
                    foreach ($leadsData as $lead) {
                        $clientEmails[] = [
                            'email' => strtolower(trim($lead->email)),
                            'nomor_hp' => $lead->mobile_phone ?? '-'
                        ];
                    }
                }
                
                if (empty($clientEmails)) {
                    continue;
                }
                
                $emails = array_column($clientEmails, 'email');
                
                // Query settlement bulan ini
                $settlementsThisMonth = DB::table('report_balance_top_up')
                    ->select(DB::raw('LOWER(TRIM(email_client)) as email'), DB::raw('SUM(CAST(total_settlement_klien AS DECIMAL(15,2))) as total'))
                    ->whereBetween('tgl_transaksi', [$startDate, $endDate])
                    ->whereNotNull('total_settlement_klien')
                    ->whereIn(DB::raw('LOWER(TRIM(email_client))'), $emails)
                    ->groupBy(DB::raw('LOWER(TRIM(email_client))'))
                    ->pluck('total', 'email')
                    ->toArray();
                
                // Query settlement akumulasi
                $settlementsAccumulated = [];
                $currentMonth = $referenceDate->month;
                if ($currentMonth > 1) {
                    $startYearDate = $referenceDate->copy()->startOfYear()->format('Y-m-d');
                    $endPreviousMonth = $referenceDate->copy()->subMonth()->endOfMonth()->format('Y-m-d');
                    
                    $settlementsAccumulated = DB::table('report_balance_top_up')
                        ->select(DB::raw('LOWER(TRIM(email_client)) as email'), DB::raw('SUM(CAST(total_settlement_klien AS DECIMAL(15,2))) as total'))
                        ->whereBetween('tgl_transaksi', [$startYearDate, $endPreviousMonth])
                        ->whereNotNull('total_settlement_klien')
                        ->whereIn(DB::raw('LOWER(TRIM(email_client))'), $emails)
                        ->groupBy(DB::raw('LOWER(TRIM(email_client))'))
                        ->pluck('total', 'email')
                        ->toArray();
                }
                
                // Insert ke summary table
                foreach ($clientEmails as $client) {
                    $email = $client['email'];
                    $totalSettlement = $settlementsThisMonth[$email] ?? 0;
                    $settlementPrevious = $settlementsAccumulated[$email] ?? 0;
                    
                    if ($totalSettlement == 0 && $settlementPrevious == 0) {
                        continue;
                    }
                    
                    $poinBulanIni = floor($totalSettlement / 250000);
                    $poinAkumulasi = floor($settlementPrevious / 250000);
                    $totalPoin = $poinBulanIni + $poinAkumulasi;
                    
                    $summaryRow = [
                        'user_id' => $canvasser->id,
                        'nama_canvasser' => $canvasser->name,
                        'email_client' => $email,
                        'nomor_hp_client' => $client['nomor_hp'],
                        'total_settlement' => $totalSettlement,
                        'poin_bulan_ini' => $poinBulanIni,
                        'poin_akumulasi' => $poinAkumulasi,
                        'poin' => $totalPoin,
                        'bulan' => $referenceDate->copy()->locale('id')->translatedFormat('F Y'),
                        'created_at' => Carbon::parse($endDate)->endOfDay(),
                        'updated_at' => now()
                    ];
                    if (Schema::hasColumn($this->summaryPanenPoinTable(), 'period_start')) {
                        $summaryRow['period_start'] = $startDate;
                    }
                    if (Schema::hasColumn($this->summaryPanenPoinTable(), 'period_end')) {
                        $summaryRow['period_end'] = $endDate;
                    }
                    DB::table($this->summaryPanenPoinTable())->insert($summaryRow);
                    
                    $totalProcessed++;
                }
            }
            
            \Log::info("Summary Panen Poin refreshed. Total records: {$totalProcessed}");
            
            return response()->json([
                'status' => 'success',
                'message' => "Summary Panen Poin updated. Total records: {$totalProcessed}"
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Error in refreshSummaryPanenPoin: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function redeemPrize(Request $request)
    {
        $respond = function (bool $status, string $message, int $httpCode = 200) use ($request) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => $status,
                    'message' => $message,
                ], $httpCode);
            }

            return redirect()->route('home')
                ->with($status ? 'success' : 'error', $message);
        };

        $request->validate([
            'prize_id' => 'required|integer|exists:prizes_v4,id',
        ]);

        $user = auth()->user();

        if (!$user) {
            return $respond(false, 'Silakan login terlebih dahulu', 401);
        }
        $today = Carbon::today();
        $redeemStartDate = $this->redeemStartDate();
        $redeemEndDate = $this->redeemEndDate();

        if ($today->lt($redeemStartDate)) {
            return $respond(false, 'Redeem hanya bisa dilakukan mulai 1 September 2026');
        }
        if ($today->gt($redeemEndDate)) {
            return $respond(false, 'Periode redeem berakhir pada 9 Oktober 2026');
        }
        $latestContact = DB::table($this->userContactInfosTable())
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();
        if (!$latestContact) {
            return $respond(false, 'Lengkapi nomor telp dan alamat terlebih dahulu', 400);
        }
        try {
            DB::transaction(function () use ($request, $user) {
                // Serialize redeem per user to prevent double-submit race.
                DB::table($this->akunPanenPoinTable())
                    ->where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                $date = $this->summaryReferenceDate();

                $redeemCountThisPeriod = $this->prizeRedeemsQuery()
                    ->where('user_id', $user->id)
                    ->whereBetween('created_at', [$this->redeemStartDate(), $this->redeemEndDate()])
                    ->count();

                $redeemPeriodLimit = 2;

                if ($redeemCountThisPeriod >= $redeemPeriodLimit) {
                    throw new \Exception("Anda sudah mencapai batas maksimal {$redeemPeriodLimit} redeem pada periode ini");
                }
                // Lock hadiah
                $prize = PrizeV4::where('id', $request->prize_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($prize->stock <= 0) {
                    throw new \Exception('Stok hadiah habis');
                }

                // Cek apakah user sudah redeem hadiah ini
                $alreadyRedeemThisPrize = $this->prizeRedeemsQuery()
                    ->where('user_id', $user->id)
                    ->where('prize_id', $request->prize_id)
                    ->whereBetween('created_at', [$this->redeemStartDate(), $this->redeemEndDate()])
                    ->exists();

                if ($alreadyRedeemThisPrize) {
                    throw new \Exception('Anda sudah pernah redeem hadiah ini');
                }

                // Lock poin user
                $userPointRecord = DB::table($this->summaryPanenPoinTable())
                    ->where('email_client', $user->email_client)
                    ->lockForUpdate();

                $userPointRecord = $this->applySummaryReferenceFilter($userPointRecord, $date)->first();

                $userPoint = (int) ($userPointRecord->poin ?? 0);
                $userPointPackage = (int) ($userPointRecord->poin_package ?? 0);
                $requiredPoint = (int) $prize->point;

                // Read actual spending inside the account lock, including September and October.
                $spentPoints = (int) $this->prizeRedeemsQuery()
                    ->where('user_id', $user->id)
                    ->whereBetween('created_at', [$this->redeemStartDate(), $this->redeemEndDate()])
                    ->sum('point_used');

                if (($userPoint + $userPointPackage - $spentPoints) < $requiredPoint) {
                    throw new \Exception('Poin tidak cukup untuk menukar hadiah ini');
                }

                // Kurangi stok
                $prize->decrement('stock');

                // Simpan redeem
                DB::table($this->prizeRedeemsTableV4())->insert([
                    'user_id' => $user->id,
                    'prize_id' => $prize->id,
                    'point_used' => $requiredPoint,
                    'period_start' => $this->redeemStartDate()->toDateString(),
                    'period_end' => $this->redeemEndDate()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update summary
                $summaryUpdate = $this->updateSummaryAfterRedeem($user->id);
                if (!$summaryUpdate['success']) {
                    throw new \RuntimeException('Gagal memperbarui poin setelah redeem.');
                }
            });

            return $respond(true, 'Hadiah berhasil ditukar');

        } catch (\Exception $e) {
            \Log::error('Redeem Error: ' . $e->getMessage());

            return $respond(false, $e->getMessage(), 400);
        }
    }

    public function storeContactInfo(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:14', 'regex:/^62[0-9]{8,12}$/'],
            'address' => 'required|string|max:255',
            'remark' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        if (!$user) {
            return redirect()->back()->with('error', 'Silakan login terlebih dahulu.');
        }

        DB::table($this->userContactInfosTable())->updateOrInsert(
            ['user_id' => $user->id],
            [
                'phone' => $request->phone,
                'address' => $request->address,
                'remark' => $request->remark,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect()->back()->with('success', 'Data kontak berhasil disimpan.');
    }



    // Update summary setelah redeem (dipanggil dari RedeemController)
    public function updateSummaryAfterRedeem($userId)
    {
        try {
            \Log::info("=== UPDATE SUMMARY AFTER REDEEM FOR USER: {$userId} ===");
            
            $referenceDate = $this->summaryReferenceDate();
            
            // Hitung pemakaian poin selama seluruh periode redeem.
            $totalPoinRedeem = $this->prizeRedeemsQuery()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$this->redeemStartDate(), $this->redeemEndDate()])
                ->sum('point_used') ?? 0;

            $akun = DB::table($this->akunPanenPoinTable())
                ->where('id', $userId)->first();
                
            \Log::info("Total poin redeem for user {$userId}: {$totalPoinRedeem}");
            
            // Update summary yang sama dengan sumber saldo pengguna.
            $latestSummaryQuery = DB::table($this->summaryPanenPoinTable())
                ->where('email_client', $akun->email_client);
            $latestSummary = $this->applySummaryReferenceFilter($latestSummaryQuery, $referenceDate)
                ->latest('created_at')->first();

            if (!$latestSummary) {
                throw new \RuntimeException('Data poin untuk periode ini tidak ditemukan.');
            }

            $updatedCount = 0;

            if ($latestSummary) {
                $poinSisa = ($latestSummary->poin_package + $latestSummary->poin) - $totalPoinRedeem;
                $remark = $this->calculateRemark($poinSisa);

                DB::table($this->summaryPanenPoinTable())
                    ->where('id', $latestSummary->id)
                    ->update([
                        'poin_redeem' => $totalPoinRedeem,
                        'remark' => $remark,
                        'updated_at' => now()
                    ]);

                $updatedCount = 1;
            }

            
            \Log::info("Updated {$updatedCount} summary records after redeem");
            
            return [
                'success' => true,
                'updated' => $updatedCount,
                'total_redeem' => $totalPoinRedeem
            ];
            
        } catch (\Exception $e) {
            \Log::error("Error in updateSummaryAfterRedeem: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    // Hitung remark berdasarkan poin sisa
    private function calculateRemark($poinSisa)
    {
        if ($poinSisa >= 0 && $poinSisa <= 100) {
            return 'Rookie';
        } elseif ($poinSisa >= 101 && $poinSisa <= 300) {
            return 'Rising Star';
        } elseif ($poinSisa >= 301) {
            return 'Champion';
        }
        return 'Rookie'; // default
    }
}
