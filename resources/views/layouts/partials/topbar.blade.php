<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">

            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="{{ url('/') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ asset('statis/images/logo.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('statis/images/logo.png') }}" alt="" height="40">
                    </span>
                </a>

                <a href="{{ url('/') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ asset('statis/images/logo.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('statis/images/logo.png') }}" alt="" height="40">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect" id="vertical-menu-btn">
                <i class="mdi mdi-menu"></i>
            </button>




        </div>

        <!-- Search input -->
        <div class="search-wrap" id="search-wrap">
            <div class="search-bar">
                <input class="search-input form-control" placeholder="Search" />
                <a href="#" class="close-search toggle-search" data-target="#search-wrap">
                    <i class="mdi mdi-close-circle"></i>
                </a>
            </div>
        </div>

        <div class="d-flex">


            <div class="dropdown d-none d-lg-inline-block ms-1">
                <button type="button" class="btn header-item noti-icon waves-effect" data-toggle="fullscreen">
                    <i class="mdi mdi-fullscreen"></i>
                </button>
            </div>

            {{-- Lonceng notifikasi admin (data dari View composer di AppServiceProvider) --}}
            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon waves-effect"
                    id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false" title="Notifikasi">
                    <i class="mdi mdi-bell-outline {{ $notifBelumDibaca ? 'bx-tada' : '' }}"></i>
                    @if ($notifBelumDibaca)
                        <span class="badge bg-danger rounded-pill">{{ $notifBelumDibaca > 99 ? '99+' : $notifBelumDibaca }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                    aria-labelledby="page-header-notifications-dropdown">
                    <div class="p-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0">Notifikasi
                                    @if ($notifBelumDibaca)
                                        <span class="badge bg-danger ms-1">{{ $notifBelumDibaca }} baru</span>
                                    @endif
                                </h6>
                            </div>
                            @if ($notifBelumDibaca)
                                <div class="col-auto">
                                    <form action="{{ route('notifikasi-admin.baca-semua') }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 small">Tandai semua dibaca</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div data-simplebar style="max-height: 300px;">
                        @forelse ($notifTerbaru as $n)
                            <a href="{{ route('notifikasi-admin.buka', $n['id']) }}"
                                class="text-reset notification-item d-block {{ $n['dibaca'] ? '' : 'bg-light' }}">
                                <div class="media">
                                    <div class="avatar-xs me-3">
                                        <span class="avatar-title bg-{{ $n['warna'] }} rounded-circle font-size-16">
                                            <i class="mdi {{ $n['ikon'] }} text-white"></i>
                                        </span>
                                    </div>
                                    <div class="media-body">
                                        <h6 class="mt-0 mb-1 {{ $n['dibaca'] ? 'text-muted' : '' }}">{{ $n['judul'] }}</h6>
                                        <div class="font-size-13 text-muted">
                                            <p class="mb-1">{{ \Illuminate\Support\Str::limit($n['pesan'], 90) }}</p>
                                            <p class="mb-0"><i class="mdi mdi-clock-outline"></i>
                                                {{ $n['waktu']?->locale('id')->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <p class="text-center text-muted small py-4 mb-0">Belum ada notifikasi.</p>
                        @endforelse
                    </div>
                    <div class="p-2 border-top">
                        <a class="btn btn-sm btn-link font-size-14 w-100 text-center" href="{{ route('notifikasi-admin.index') }}">
                            <i class="mdi mdi-arrow-right-circle me-1"></i> Lihat semua notifikasi
                        </a>
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img class="rounded-circle header-profile-user" src="{{ asset('statis/images/users/user.png') }}"
                        alt="Header Avatar">
                    <span class="d-none d-xl-inline-block ms-1">Super Admin</span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <!-- item-->
                    {{-- <a class="dropdown-item" href="#"><i
                            class="mdi mdi-account-circle-outline font-size-16 align-middle me-1"></i> Profile</a>
                    <a class="dropdown-item" href="#"><i
                            class="mdi mdi-wallet-outline font-size-16 align-middle me-1"></i> My Wallet</a>
                    <a class="dropdown-item d-block" href="#"><span
                            class="badge badge-success float-end">11</span><i
                            class="mdi mdi-cog-outline font-size-16 align-middle me-1"></i> Settings</a>
                    <a class="dropdown-item" href="#"><i
                            class="mdi mdi-lock-open-outline font-size-16 align-middle me-1"></i> Lock screen</a> --}}
                    {{-- <div class="dropdown-divider"></div> --}}


                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>

                    <a class="dropdown-item text-danger" href="#"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="mdi mdi-power font-size-16 align-middle me-1 text-danger"></i>
                        Logout
                    </a>

                </div>
            </div>

            {{-- <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon right-bar-toggle waves-effect">
                    <i class="mdi mdi-cog-outline font-size-20"></i>
                </button>
            </div> --}}

        </div>
    </div>
</header>
