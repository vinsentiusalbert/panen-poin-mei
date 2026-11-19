<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Panen Poin UMKM Area 3')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/panen-umkm.css') }}">
    @stack('styles')
</head>
<body class="um-body">

<nav class="um-nav">
    <div class="container d-flex align-items-center justify-content-between">
        <a class="um-brand" href="{{ route('umkm.area3') }}">
            <img src="{{ asset('img/assets/myads.png') }}" height="26" alt="MyAds">
            <span><strong>Panen Poin UMKM</strong><small>Area 3</small></span>
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="#periode" class="um-nav-link d-none d-sm-inline">Periode</a>
            <a href="#leaderboard" class="um-nav-link d-none d-sm-inline">Peringkat</a>
            <a href="#hadiah" class="um-nav-link d-none d-sm-inline">Hadiah</a>
            @guest('web')
                <button class="btn um-btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
            @else
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button class="btn um-btn-ghost btn-sm" type="submit">Logout</button>
                </form>
            @endguest
        </div>
    </div>
</nav>

<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content um-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Masuk ke Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Login untuk melihat poin dan menukarkan hadiah.</p>
                    <input type="email" name="email" class="form-control um-input mb-3" placeholder="email@example.com" required>
                    <input type="password" name="password" class="form-control um-input" placeholder="Password" required>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn um-btn-green w-100">Login</button>
                </div>
            </form>
        </div>
    </div>
</div>

@yield('content')

<footer class="um-footer">
    <div class="container">© 2026 MyAds · Panen Poin UMKM Area 3 · 13 November – 31 Desember 2026</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
