@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="page-content">

        @include('layouts.partials.pagetitle', [
            'pagetitle' => 'CMS',
            'subtitle' => 'Notifikasi',
            'title' => 'Notifikasi',
            'action' => null,
        ])

        <div class="container-fluid">
            <div class="page-content-wrapper">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('notifikasi-admin.index') }}"
                                    class="btn {{ $filter === 'semua' ? 'btn-primary' : 'btn-light' }}">Semua</a>
                                <a href="{{ route('notifikasi-admin.index', ['filter' => 'belum']) }}"
                                    class="btn {{ $filter === 'belum' ? 'btn-primary' : 'btn-light' }}">Belum dibaca</a>
                            </div>
                            <form action="{{ route('notifikasi-admin.baca-semua') }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="mdi mdi-check-all"></i> Tandai semua dibaca
                                </button>
                            </form>
                        </div>

                        <div class="list-group">
                            @forelse ($daftar as $n)
                                <a href="{{ route('notifikasi-admin.buka', $n['id']) }}"
                                    class="list-group-item list-group-item-action d-flex gap-3 align-items-start {{ $n['dibaca'] ? '' : 'bg-light' }}">
                                    <span class="avatar-xs flex-shrink-0">
                                        <span class="avatar-title bg-{{ $n['warna'] }} rounded-circle font-size-16">
                                            <i class="mdi {{ $n['ikon'] }} text-white"></i>
                                        </span>
                                    </span>
                                    <span class="flex-grow-1">
                                        <span class="d-flex justify-content-between gap-2">
                                            <strong class="{{ $n['dibaca'] ? 'text-muted fw-normal' : '' }}">{{ $n['judul'] }}</strong>
                                            <small class="text-muted text-nowrap"
                                                title="{{ $n['waktu']?->timezone(config('muk.zona_waktu'))->format('d/m/Y H:i') }} WIB">
                                                {{ $n['waktu']?->locale('id')->diffForHumans() }}
                                            </small>
                                        </span>
                                        <span class="d-block small text-muted">{{ $n['pesan'] }}</span>
                                    </span>
                                    @unless ($n['dibaca'])
                                        <span class="badge bg-danger rounded-pill align-self-center">Baru</span>
                                    @endunless
                                </a>
                            @empty
                                <p class="text-center text-muted py-4 mb-0">
                                    {{ $filter === 'belum' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}
                                </p>
                            @endforelse
                        </div>

                        <div class="mt-3">{{ $notifikasi->links('pagination::bootstrap-5') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
