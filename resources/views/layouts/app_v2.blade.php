<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'MyAds Reward League V2')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/reward-v2.css') }}">
    @stack('styles')

    <!-- Modal Login Style -->
    <style>
        /* ===== LOGIN MODAL STYLE ===== */
        .modal-login .modal-content {
            background: #ffffff;
            box-shadow: 0 25px 60px rgba(0,0,0,.25);
            transform: scale(.85);
            opacity: 0;
            transition: all .4s ease;
            border-radius: 16px;
        }

        .modal-login.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .form-login {
            border-radius: 10px;
            padding: 12px 14px;
            border: 1px solid #ddd;
        }

        .form-login:focus {
            border-color: #0b3a45;
            box-shadow: 0 0 0 .2rem rgba(11,58,69,.15);
        }

        .btn-login {
            background-color: #0b3a45;
            color: #fff;
            border-radius: 12px;
            padding: 12px;
            transition: all .3s ease;
        }

        .btn-login:hover {
            background-color: #09515f;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(11,58,69,.35);
            color: #fff;
        }
    </style>
</head>

<body>

<!-- ================= NAVBAR ================= -->
<nav class="navbar navbar-expand-lg navbar-dark reward-navbar-shell">
    <div class="container">
        <div class="reward-navbar-inner">
            <a class="navbar-brand reward-brand" href="{{ route('home') }}">
                <img src="{{ asset('img/assets/myads.png') }}" height="28" alt="MyAds">
                <span class="reward-brand__text">
                    <strong>Reward League V2</strong>
                    <small>Festive Edition</small>
                </span>
            </a>

            <div class="reward-navbar-actions ms-auto">
            @guest('web')
                <button class="btn reward-login-trigger fw-semibold"
                        data-bs-toggle="modal"
                        data-bs-target="#loginModal">
                    Login
                </button>
            @else
                <div class="d-flex justify-content-end align-items-center gap-2">
                    <a href="#prizes" class="reward-link reward-link--pill">
                        Reward
                    </a>
                    <div class="dropdown">
                        <a href="#"
                        class="nav-link dropdown-toggle reward-user-toggle"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                            <span class="reward-user-toggle__label">Hi, {{ auth()->user()->nama_akun ?? auth()->user()->email_client }}</span>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow reward-dropdown-menu">
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                            class="dropdown-item reward-dropdown-item text-danger fw-semibold">
                                        Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            @endguest
            </div>
        </div>
    </div>
</nav>

<!-- ================= LOGIN MODAL ================= -->
<div class="modal fade modal-login" id="loginModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Masuk ke Akun V2</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="modal-body pt-2">
                    <p class="text-muted mb-4">
                        Silakan login untuk menukarkan reward dan melihat progres liga.
                    </p>

                    <div class="mb-3">
                        <label class="form-label text-dark">Email</label>
                        <input type="email"
                               name="email"
                               class="form-control form-login"
                               placeholder="email@example.com"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark">Password</label>
                        <input type="password"
                               name="password"
                               class="form-control form-login"
                               placeholder="••••••••"
                               required>
                    </div>

                    {{-- <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember">
                        <label class="form-check-label text-muted">
                            Remember me
                        </label>
                    </div> --}}
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn btn-login w-100 fw-semibold">
                        Login
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ================= CONTENT ================= -->
@yield('content')
{{-- asdasdas
{{ dd(auth()->check(), auth()->user()) }}
asdasd --}}
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@stack('scripts')
</body>
</html>
