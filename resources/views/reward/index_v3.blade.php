@extends('layouts.app_v3')

@section('title', 'MyAds Reward League V3')

@section('content')
<div class="container my-5">
@php
    $user = auth()->user();
    $data = array_merge([
        'poin_under_100' => [],
        'poin_101_300' => [],
        'poin_over_301' => [],
    ], is_array($data ?? null) ? $data : []);
    $prizeImageUrl = static function ($path) {
        return str_starts_with($path, 'hadiah/')
            ? asset($path)
            : asset('img/' . $path);
    };
@endphp

@if(session('success'))
<div class="alert alert-success reward-alert" role="alert">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger reward-alert" role="alert">
    {{ session('error') }}
</div>
@endif

<div class="world-cup-hero-decor">
    <img src="{{ asset('assets/world-cup-trophy.svg') }}" alt="" class="world-cup-hero-decor__trophy" aria-hidden="true">
    <img src="{{ asset('assets/world-cup-ball.svg') }}" alt="" class="world-cup-hero-decor__ball" aria-hidden="true">
</div>

<div class="eid-stars eid-stars--left" aria-hidden="true">
    <span class="eid-star eid-star--lg"></span>
    <span class="eid-star eid-star--md"></span>
    <span class="eid-star eid-star--sm"></span>
</div>

<div class="eid-stars eid-stars--right" aria-hidden="true">
    <span class="eid-star eid-star--md"></span>
    <span class="eid-star eid-star--lg"></span>
    <span class="eid-star eid-star--sm"></span>
</div>

{{-- ================= LIGA ================= --}}
@if(isset($point) && is_object($point) && isset($point->poin))
<div class="section-card text-center mb-5">
    <h2 class="mb-4">Badges</h2>

    {{-- <div class="row justify-content-center g-4">
        <div class="col-md-4 liga-card">
            <img src="{{ asset('img/rookie.png') }}">
            <h5>Rookie</h5>
            <span class="liga-range">0 – 100 Poin</span>
        </div>
        <div class="col-md-4 liga-card">
            <img src="{{ asset('img/rising_star.png') }}">
            <h5>Rising Star</h5>
            <span class="liga-range">101 – 200 Poin</span>
        </div>
        <div class="col-md-4 liga-card">
            <img src="{{ asset('img/champion.png') }}">
            <h5>Champion</h5>
            <span class="liga-range">201 – 300 Poin</span>
        </div>
    </div> --}}
    
        <div class="mt-4">
            @php
                $percent = min(($point->poin / 300) * 100, 100);
            @endphp
            <div class="row justify-content-center g-4">
                @if($point->poin >= 0 && $point->poin <= 100)
                <div class="col-md-4 liga-card">
                    <img src="{{ asset('img/rookie.png') }}">
                    <h5>Rookie</h5>
                    
                </div>
                @elseif($point->poin >= 101 && $point->poin <= 200)
                <div class="col-md-4 liga-card">
                    <img src="{{ asset('img/rising_star.png') }}">
                    <h5>Rising Star</h5>
                    
                </div>
                @elseif($point->poin >= 201)
                <div class="col-md-4 liga-card">
                    <img src="{{ asset('img/champion.png') }}">
                    <h5>Champion</h5>
                    
                </div>
                @endif
            </div>
            <div class="progress">
                <div 
                    class="progress-bar progress-animate"
                    data-percent="{{ $percent }}"
                    style="width: 0%">
                </div>
            </div>

            <small>Total Poin Anda: <b>{{ $point->poin }}</b></small>

            @if($user)
                <div class="mt-3">
                    <button type="button" class="btn btn-contact fw-semibold" data-bs-toggle="modal" data-bs-target="#contactInfoModal">
                        Isi Alamat Pengiriman
                    </button>
                </div>
                @if($userContactInfos->isNotEmpty())
                    @php
                        $latestContact = $userContactInfos->first();
                    @endphp
                    <small class="contact-summary d-block mt-2">
                        Kontak tersimpan: <span>{{ $latestContact->phone }}</span> | <span>{{ $latestContact->address }}</span>
                    </small>
                    @if(!empty($latestContact->remark))
                        <small class="contact-summary d-block mt-1">
                            Remark: <span>{{ $latestContact->remark }}</span>
                        </small>
                    @endif
                @else
                    <small class="contact-summary d-block mt-2">Belum ada data kontak tersimpan.</small>
                @endif
            @else
                <small class="contact-summary d-block mt-3">Login untuk mengisi data kontak.</small>
            @endif
        </div>

</div>

    @endif

@if($user)
<div class="section-card mb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <div>
            <h4 class="mb-1">Riwayat Redeem Saya</h4>
            <small class="contact-summary">Cek reward yang sudah pernah diredeem dan lihat bukti kirimnya di sini.</small>
        </div>
        <span class="history-counter">{{ $userRedeemHistory->count() }} Redeem</span>
    </div>

    @if($userRedeemHistory->isNotEmpty())
        <div class="row g-4">
            @foreach($userRedeemHistory as $redeem)
                @php
                    $historyShippingProofUrl = $redeem->shipping_proof_path
                        ? asset('storage/'.$redeem->shipping_proof_path)
                        : null;
                    $historyShippingProofExtension = $redeem->shipping_proof_path
                        ? strtolower(pathinfo($redeem->shipping_proof_path, PATHINFO_EXTENSION))
                        : null;
                    $historyShippingProofIsImage = in_array($historyShippingProofExtension, ['jpg', 'jpeg', 'png']);
                    $historyReceiveProofUrl = $redeem->proof_path
                        ? asset('storage/'.$redeem->proof_path)
                        : null;
                    $historyReceiveProofExtension = $redeem->proof_path
                        ? strtolower(pathinfo($redeem->proof_path, PATHINFO_EXTENSION))
                        : null;
                    $historyReceiveProofIsImage = in_array($historyReceiveProofExtension, ['jpg', 'jpeg', 'png']);
                @endphp
                <div class="col-lg-6">
                    <div class="redeem-history-card h-100">
                        <div class="redeem-history-head">
                            <div class="redeem-history-prize">
                                <div class="redeem-history-thumb">
                                    <img src="{{ $prizeImageUrl($redeem->prize_image) }}" alt="{{ $redeem->prize_name }}">
                                </div>
                                <div>
                                    <h5 class="mb-1">{{ $redeem->prize_name }}</h5>
                                    <div class="redeem-history-meta">{{ $redeem->prize_point }} poin</div>
                                </div>
                            </div>
                            <span class="redeem-status-badge {{ $redeem->shipped_at ? 'is-shipped' : 'is-pending' }}">
                                {{ $redeem->shipped_at ? 'Terkirim' : 'Diproses' }}
                            </span>
                        </div>

                        <div class="redeem-history-info">
                            <div class="redeem-history-line">
                                <span>Redeem</span>
                                <strong>{{ \Carbon\Carbon::parse($redeem->created_at)->format('d M Y') }}</strong>
                            </div>
                            <div class="redeem-history-line">
                                <span>Tanggal kirim</span>
                                <strong>{{ $redeem->shipped_at ? \Carbon\Carbon::parse($redeem->shipped_at)->format('d M Y') : '-' }}</strong>
                            </div>
                            <div class="redeem-history-line">
                                <span>Bukti terima</span>
                                <strong>{{ $redeem->proof_path ? 'Sudah upload' : 'Belum upload' }}</strong>
                            </div>
                        </div>

                        <div class="redeem-history-proof mt-3">
                            <div class="redeem-history-proof-title">Bukti kirim</div>
                            @if($historyShippingProofUrl)
                                @if($historyShippingProofIsImage)
                                    <a href="{{ $historyShippingProofUrl }}" target="_blank" class="shipping-proof-link">
                                        <img
                                            src="{{ $historyShippingProofUrl }}"
                                            alt="Bukti kirim {{ $redeem->prize_name }}"
                                            class="shipping-proof-image"
                                        >
                                    </a>
                                @else
                                    <a class="btn btn-outline-light btn-sm mt-2" href="{{ $historyShippingProofUrl }}" target="_blank">
                                        Lihat Bukti Kirim
                                    </a>
                                @endif
                            @else
                                <div class="redeem-history-empty">Bukti kirim belum tersedia.</div>
                            @endif
                        </div>

                        <div class="redeem-history-proof mt-3">
                            <div class="redeem-history-proof-title">Bukti terima</div>
                            @if($historyReceiveProofUrl)
                                @if($historyReceiveProofIsImage)
                                    <a href="{{ $historyReceiveProofUrl }}" target="_blank" class="shipping-proof-link">
                                        <img
                                            src="{{ $historyReceiveProofUrl }}"
                                            alt="Bukti terima {{ $redeem->prize_name }}"
                                            class="shipping-proof-image"
                                        >
                                    </a>
                                @else
                                    <a class="btn btn-outline-light btn-sm mt-2" href="{{ $historyReceiveProofUrl }}" target="_blank">
                                        Lihat Bukti Terima
                                    </a>
                                @endif
                            @else
                                <div class="redeem-history-empty">Bukti terima belum tersedia.</div>
                            @endif

                            <form method="POST" action="{{ route('redeem.proof') }}" enctype="multipart/form-data" class="d-flex flex-column gap-2 mt-3">
                                @csrf
                                <input type="hidden" name="redeem_id" value="{{ $redeem->id }}">
                                <input type="hidden" name="source_version" value="{{ $redeem->source_version }}">
                                <input type="file" name="proof" class="form-control contact-input" accept=".jpg,.jpeg,.png,.pdf" required>
                                <button type="submit" class="btn btn-contact btn-sm align-self-start">
                                    {{ $redeem->proof_path ? 'Ganti Bukti Terima' : 'Upload Bukti Terima' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="redeem-history-empty">
            Belum ada reward yang diredeem.
        </div>
    @endif
</div>
@endif

{{-- ================= TABLE ================= --}}
@php
    $leagueSections = [
        'poin_over_301' => ['title' => 'CHAMPION', 'range' => '> 301 Poin', 'class' => 'champion', 'liga' => 'Champion', 'medal' => 'medali 3-01.png'],
        'poin_101_300' => ['title' => 'RISING STAR', 'range' => '101 - 300 Poin', 'class' => 'rising', 'liga' => 'Rising Star', 'medal' => 'Medali 2-01.png'],
        'poin_under_100' => ['title' => 'ROOKIE', 'range' => '< 100 Poin', 'class' => 'rookie', 'liga' => 'Rookie', 'medal' => 'medali 1-01.png'],
    ];
@endphp

@foreach($leagueSections as $key => $section)
<section class="ranking-board ranking-board--{{ $section['class'] }} mb-5 scroll-animate">
    <div class="ranking-board__paper">
        <div class="ranking-board__edge ranking-board__edge--left"></div>
        <div class="ranking-board__edge ranking-board__edge--right"></div>

        <div class="ranking-board__hero">
            <div class="ranking-board__hero-title">{{ $section['title'] }}</div>
            <div class="ranking-board__hero-pill">{{ $section['range'] }}</div>
        </div>

        <div class="ranking-board__body">
            <div class="ranking-board__medal d-none d-lg-flex">
                <img
                    src="{{ asset('assets/' . $section['medal']) }}"
                    alt="Medali {{ $section['liga'] }}"
                    class="ranking-board__medal-item ranking-board__medal-item--{{ $section['class'] }}"
                >
            </div>

            <div class="ranking-board__table-wrap">
                <div class="ranking-board__header">
                    <span>No</span>
                    <span>Nama Akun</span>
                    <span>Canvasser</span>
                    <span>Point</span>
                    <span>Liga</span>
                </div>

                <div class="ranking-board__rows">
                    @forelse ($data[$key] as $index => $row)
                        @php
                            $email = $row['email_client'];
                            [$name, $domain] = explode('@', $email);
                            $maskedName = substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 0));
                            $maskedDomain = substr($domain, 0, 2) . str_repeat('*', max(strlen($domain) - 2, 0));
                            $maskedAkun = substr($row['nama_akun'], 0, 2) . str_repeat('*', max(strlen($row['nama_akun']) - 2, 0));
                        @endphp

                        <div class="ranking-row {{ $index < 3 ? 'ranking-row--podium ranking-row--podium-' . ($index + 1) : '' }}">
                            <div class="ranking-row__no">
                                <span class="ranking-row__no-text">{{ $index + 1 }}</span>
                            </div>
                            <div class="ranking-row__account">
                                <strong>{{ $maskedAkun }}</strong>
                                <small>{{ $maskedName . '@' . $maskedDomain }}</small>
                            </div>
                            <div class="ranking-row__canvasser">{{ $row['nama_canvasser'] }}</div>
                            <div class="ranking-row__point">
                                <span class="ranking-row__point-number">{{ $row['poin'] }}</span>
                                <span class="ranking-row__point-unit">PTS</span>
                            </div>
                            <div class="ranking-row__liga">{{ strtoupper($section['liga']) }}</div>
                        </div>
                    @empty
                        <div class="ranking-row ranking-row--empty">
                            <div class="ranking-row__empty-text">Data belum tersedia</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endforeach



{{-- ================= PRIZE ================= --}}
<div class="section-card" id="prizes">
    <div class="prize-section-head mb-4">
        <div>
            <h4 class="mb-1">Hadiah yang Bisa Diredeem</h4>
            <p class="prize-section-copy mb-0">Pilih hadiah favoritmu. Redeem aktif dari 1 Juli 2026 sampai 11 Agustus 2026 dengan maksimal 2 hadiah per user.</p>
        </div>
        <div class="prize-section-pill">
            1 Juli - 11 Agustus 2026
        </div>
    </div>

    <div class="row g-4 prize-wrapper">
@foreach($prizes as $p)
    @php
        $user = auth()->user();
        $notLogin = !auth()->check();
        $outOfStock = $p->stock <= 0;
        $redeemCount = $redeemCounts[$p->id] ?? 0;
        $hasContactInfo = isset($userContactInfos) && $userContactInfos->isNotEmpty();
        $userCurrentPoint = (int) ($point->poin ?? 0);
        $pointEnough = $userCurrentPoint >= (int) $p->point;
        $limitReached = isset($totalRedeemThisMonth, $redeemMonthlyLimit) && $totalRedeemThisMonth >= $redeemMonthlyLimit;
        $alreadyRedeemed = $redeemCount > 0;
        $redeemDisabled = $notLogin || !($isRedeemPeriod ?? false) || $outOfStock || !$pointEnough || !$hasContactInfo || $limitReached || $alreadyRedeemed;
        $redeemLabel = 'Redeem';

        if ($notLogin) {
            $redeemLabel = 'Login untuk Redeem';
        } elseif (!($isRedeemPeriod ?? false)) {
            $redeemLabel = ($isRedeemEnded ?? false)
                ? 'Periode Redeem Selesai'
                : 'Redeem Mulai ' . (($redeemStartDate ?? null)?->format('d M Y') ?? 'Juni 2026');
        } elseif ($outOfStock) {
            $redeemLabel = 'Stok Habis';
        } elseif ($alreadyRedeemed) {
            $redeemLabel = 'Sudah Diredeem';
        } elseif ($limitReached) {
            $redeemLabel = 'Limit Redeem Tercapai';
        } elseif (!$hasContactInfo) {
            $redeemLabel = 'Isi Alamat Dulu';
        } elseif (!$pointEnough) {
            $redeemLabel = 'Poin Tidak Cukup';
        }
        $cardStateClass = $alreadyRedeemed ? 'prize-card--redeemed' : ($outOfStock ? 'prize-card--soldout' : '');
    @endphp

    <div class="col-sm-6 col-xl-4">
            <div class="prize-card p-4 {{ $cardStateClass }}">
            <div class="prize-card__body">
                <div class="prize-card__topline">
                    <span class="prize-stock-badge {{ $outOfStock ? 'is-empty' : '' }}">
                        {{ $outOfStock ? 'Stok Habis' : 'Stok ' . $p->stock . ' Unit' }}
                    </span>
                    <span class="point-badge">
                        {{ $p->point }} Poin
                    </span>
                </div>

                <div class="prize-image">
                    <img src="{{ $prizeImageUrl($p->img) }}" alt="{{ $p->name }}">
                </div>

                <div class="prize-title">
                    {{ $p->name }}
                </div>

                <div class="prize-meta-grid">
                    <div class="prize-meta-card">
                        <span class="prize-meta-label">Status</span>
                        <strong>{{ $alreadyRedeemed ? 'Sudah Redeem' : ($outOfStock ? 'Habis' : 'Tersedia') }}</strong>
                    </div>
                </div>
            </div>

            <div class="prize-card__footer mt-4">
                @if(!$redeemDisabled)
                    <form method="POST" action="{{ route('redeem') }}">
                        @csrf
                        <input type="hidden" name="prize_id" value="{{ $p->id }}">
                        <button type="submit" class="btn prize-redeem-btn w-100">
                            {{ $redeemLabel }}
                        </button>
                    </form>
                @elseif($user && !$hasContactInfo)
                    <button type="button" class="btn prize-redeem-btn prize-redeem-btn--secondary w-100" data-bs-toggle="modal" data-bs-target="#contactInfoModal">
                        {{ $redeemLabel }}
                    </button>
                @else
                    <button type="button" class="btn prize-redeem-btn prize-redeem-btn--disabled w-100" disabled>
                        {{ $redeemLabel }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endforeach



    </div>

</div>

</div>

<!-- Modal Contact Info -->
<div class="modal fade contact-modal" id="contactInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content contact-modal-content">
            @php
                $savedContact = $userContactInfos->first();
            @endphp
            <div class="modal-header">
                <h5 class="modal-title">Isi Nomor Telp & Alamat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('contact-info.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nomor Telp</label>
                        <input type="text"
                               name="phone"
                               class="form-control contact-input"
                               placeholder="62xxxxxxxxx"
                               value="{{ old('phone', $savedContact->phone ?? '') }}"
                               inputmode="numeric"
                               minlength="10"
                               maxlength="14"
                               pattern="^62[0-9]{8,12}$"
                               required>
                        <small>*) Nomor untuk OVO / Gopay / Link Aja</small>
                        {{-- <small class="text-muted">Format: 62 + 8-12 digit (total 10-14 digit)</small> --}}
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" class="form-control contact-input" rows="3" required>{{ old('address', $savedContact->address ?? '') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remark</label>
                        <textarea
                            name="remark"
                            class="form-control contact-input"
                            rows="3"
                            placeholder="Tulis catatan pengiriman atau permintaan khusus"
                        >{{ old('remark', $savedContact->remark ?? '') }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-contact">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    const cards = document.querySelectorAll(".prize-card");

    const observer = new IntersectionObserver(entries => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.animationDelay = `${index * 0.15}s`;
                entry.target.classList.add("animate");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    cards.forEach(card => observer.observe(card));
    
});
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.progress-animate').forEach(bar => {
        const percent = bar.dataset.percent;
        setTimeout(() => {
            bar.style.width = percent + '%';
        }, 200); // delay dikit biar kelihatan animasinya
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("show");
                    observer.unobserve(entry.target); // animate once
                }
            });
        },
        {
            threshold: 0.2
        }
    );

    document.querySelectorAll(".scroll-animate").forEach(el => {
        observer.observe(el);
    });
});

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".ranking-board").forEach((board) => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.18 });

        observer.observe(board);
    });
});


</script>
@endpush
