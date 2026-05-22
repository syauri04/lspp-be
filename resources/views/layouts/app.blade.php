<!doctype html>
<html lang="en">

<head>
    @include('layouts.partials.titlemeta', ['title' => 'CMS'])
    <link href="{{ asset('assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css') }}" rel="stylesheet"
        type="text/css" />

    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Page specific CSS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>


<body>

    <!-- Begin page -->
    <div id="layout-wrapper">

        @include('layouts.partials.topbar')

        <!-- ========== Left Sidebar Start ========== -->
        @include('layouts.partials.sidebar')
        <!-- Left Sidebar End -->

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">


            @yield('content')
            <!-- End Page-content -->
            @include('layouts.partials.footer')
        </div>
        <!-- end main content-->

    </div>
    <!-- END layout-wrapper -->

    @include('layouts.partials.rightsidebar')
    @include('layouts.partials.vendor')

    @stack('scripts')

</body>

</html>
