<!doctype html>
<html lang="en">

<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="authentication-bg bg-custom">
    <div class="home-center">
        <div class="home-desc-center">
            <div class="container">
                <div class="home-btn">
                    <a href="{{ url('/') }}" class="text-white router-link-active"><i class="fas fa-home h2"></i></a>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card">
                            <div class="card-body">
                                <div class="px-2 py-3">
                                    <div class="text-center">
                                        <a href="{{ url('/') }}">
                                            <img src="{{ asset('statis/images/logo.png') }}" height="80"
                                                alt="logo" />
                                        </a>

                                        <h5 class="text-custom mb-2 mt-4">Welcome Back !</h5>
                                        <p class="text-muted">
                                            Sign in to continue to Dashboard.
                                        </p>
                                    </div>
                                    <div class="text-center mb-3">
                                        @if ($errors->any())
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <ul class="mb-0">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                    aria-label="Close"></button>
                                            </div>
                                        @endif

                                        @if (session('status'))
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                {{ session('status') }}
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                    aria-label="Close"></button>
                                            </div>
                                        @endif

                                        @if (session('error'))
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                {{ session('error') }}
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                    aria-label="Close"></button>
                                            </div>
                                        @endif
                                    </div>
                                    <form class="form-horizontal mt-4 pt-2" action="{{ route('login') }}"
                                        method="POST">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="email">Email</label>
                                            <input type="email" name="email" class="form-control" id="email"
                                                placeholder="Enter email" required autofocus />
                                        </div>

                                        <div class="mb-3">
                                            <label for="password">Password</label>
                                            <input type="password" name="password" class="form-control" id="password"
                                                placeholder="Enter password" required>
                                        </div>

                                        {{-- <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input"
                                                    id="customControlInline" />
                                                <label class="form-label" for="customControlInline">Remember me</label>
                                            </div>
                                        </div> --}}

                                        <div>
                                            <button class="btn btn-custom w-100 waves-effect waves-light"
                                                type="submit">
                                                Log In
                                            </button>
                                        </div>

                                        {{-- <div class="mt-4 text-center">
                                            <a href="auth-recoverpw.html" class="text-muted"><i
                                                    class="mdi mdi-lock me-1"></i> Forgot your
                                                password?</a>
                                        </div> --}}
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- <div class="mt-5 text-center text-white">
                            <p>
                                Don't have an account ?<a href="auth-register.html" class="fw-bold text-white">
                                    Register</a>
                            </p>
                        </div> --}}
                    </div>
                </div>
            </div>
        </div>
        <!-- End Log In page -->
    </div>


</body>

</html>
