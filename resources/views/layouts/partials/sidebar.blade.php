<!-- ========== Left Sidebar Start ========== -->
<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div class="user-sidebar text-center">
            <div class="dropdown">
                <div class="user-img">
                    <img src="{{ asset('statis/images/users/user.png') }}" alt="" class="rounded-circle" />
                    <span class="avatar-online bg-success"></span>
                </div>
                <div class="user-info">
                    <h5 class="mt-3 font-size-16 text-white">LSPP 306</h5>
                    <span class="font-size-13 text-white-50">Super Admin </span>
                </div>
            </div>
        </div>

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title">Beranda</li>

                <li>
                    <a href="{{ url('/dashboard') }}" class="waves-effect">
                        <i class="dripicons-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-title">Management Sertifikasi</li>
                <li>
                    <a href="{{ route('pendaftaran.index') }}" class="waves-effect">
                        <i class="dripicons-checklist"></i>
                        <span>Pendaftaran Sertifikasi</span>
                    </a>
                </li>

                <li class="menu-title">Content Website</li>
                <li>
                    <a href="{{ route('hero-banner.index') }}" class="waves-effect">
                        <i class="dripicons-device-desktop"></i>
                        <span>Hero Banner</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('about-page.edit') }}" class="waves-effect">
                        <i class="dripicons-document"></i>
                        <span>Tentang Kami</span>
                    </a>
                </li>

                <li>
                    <a href="javascript: void(0);"
                        class="has-arrow waves-effect  {{ request()->segment(1) === 'sertifikat' ? 'mm-active' : '' }}">
                        <i class="dripicons-card"></i>
                        <span>Skema Sertifikasi</span>
                    </a>
                    <ul class="sub-menu {{ request()->segment(1) === 'sertifikat' ? 'mm-collapse mm-show' : '' }}">
                        <li class="{{ request()->segment(2) === 'skema-categories' ? 'mm-active' : '' }}"><a
                                href="{{ route('skema-categories.index') }}">Kategori Skema</a></li>
                        <li class="{{ request()->segment(2) === 'skema-sertifikasi' ? 'mm-active' : '' }}"><a
                                href="{{ route('skema-sertifikasi.index') }}">Skema Sertifikasi</a></li>
                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);"
                        class="has-arrow waves-effect  {{ request()->segment(1) === 'organizational' ? 'mm-active' : '' }}">
                        <i class="dripicons-network-3"></i>
                        <span>Struktur Organisasi</span>
                    </a>
                    <ul class="sub-menu {{ request()->segment(1) === 'organizational' ? 'mm-collapse mm-show' : '' }}">
                        <li class="{{ request()->segment(2) === 'divisions' ? 'mm-active' : '' }}"><a
                                href="{{ route('divisions.index') }}">Divisi</a></li>
                        <li class="{{ request()->segment(2) === 'members' ? 'mm-active' : '' }}"><a
                                href="{{ route('members.index') }}">Anggota</a></li>
                    </ul>
                </li>

                <li>
                    <a href="{{ route('tuks.index') }}" class="waves-effect">
                        <i class="dripicons-map"></i>
                        <span>Lokasi TUK</span>
                    </a>
                </li>

                <li>
                    <a href="javascript: void(0);"
                        class="has-arrow waves-effect  {{ request()->segment(1) === 'news' ? 'mm-active' : '' }}">
                        <i class=" dripicons-article"></i>
                        <span>Berita</span>
                    </a>
                    <ul class="sub-menu {{ request()->segment(1) === 'news' ? 'mm-collapse mm-show' : '' }}">
                        <li class="{{ request()->segment(2) === 'categories' ? 'mm-active' : '' }}"><a
                                href="{{ route('news-categories.index') }}">Kategori</a></li>
                        <li class="{{ request()->segment(2) === 'articles' ? 'mm-active' : '' }}"><a
                                href="{{ route('news-articles.index') }}">Artikel</a></li>
                    </ul>
                </li>



                <li>
                    <a href="{{ route('gallery-albums.index') }}" class="waves-effect">
                        <i class="dripicons-photo-group"></i>
                        <span>Galeri Albums</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('calendars.index') }}" class="waves-effect">
                        <i class="dripicons-calendar"></i>
                        <span>Kalender</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('faqs.index') }}" class="waves-effect">
                        <i class="dripicons-checklist"></i>
                        <span>FAQ</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('mitras.index') }}" class="waves-effect">
                        <i class="dripicons-feed"></i>
                        <span>Mitra</span>
                    </a>
                </li>

                <li class="menu-title">Users</li>
                <li>
                    <a href="{{ route('asesis.index') }}" class="waves-effect">
                        <i class="dripicons-checklist"></i>
                        <span>Daftar Asesi</span>
                    </a>
                </li>


            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>
<!-- Left Sidebar End -->
