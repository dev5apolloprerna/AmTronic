<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vendor & Quotation Manager</title>
    {{-- ?v= forces the browser to load the latest CSS after every change --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-logo">
            <img src="{{ asset('images/logo.png') }}" alt="Amtronics - Driven by Stability. Powered by Trust.">
        </div>

        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to manage vendors, quotations &amp; invoices</p>

            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group checkbox-row">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember" style="margin:0; font-weight:400;">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>
        </div>

        <p class="auth-footer">&copy; {{ date('Y') }} Amtronics. All rights reserved.</p>
    </div>
</body>
</html>