<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Vendor & Quotation Manager</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('styles')
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand"><img src="{{ asset('images/glass-grip-logo.png') }}" alt="Glass Grip"></div>
            <nav class="sidebar-nav">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>

                <div class="sidebar-section">Sales</div>
                <a href="{{ route('quotations.index') }}" class="{{ request()->routeIs('quotations.*') ? 'active' : '' }}">Quotations / Invoices</a>
                <a href="{{ route('payment-collections.index') }}" class="{{ request()->routeIs('payment-collections.*') ? 'active' : '' }}">Payment Collection</a>
                @if(auth()->user()->isSuperAdmin())
                    <div class="sidebar-section">Masters</div>
                    <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">Company</a>
                    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">Products</a>
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Users</a>
                    <a href="{{ route('number-settings.index') }}" class="{{ request()->routeIs('number-settings.*') ? 'active' : '' }}">Number Settings</a>

                    <div class="sidebar-section">Reports</div>
                    <a href="{{ route('reports.customer-ledger') }}" class="{{ request()->routeIs('reports.customer-ledger') ? 'active' : '' }}">Customer Ledger</a>
                    <a href="{{ route('pending-amounts.index') }}" class="{{ request()->routeIs('pending-amounts.*') ? 'active' : '' }}">Pending Amounts</a>
                    <a href="{{ route('reports.sales') }}" class="{{ request()->routeIs('reports.sales') ? 'active' : '' }}">Sales Report</a>

                @endif
            </nav>
        </aside>

        <div class="main">
            <header class="topbar">
                <div style="display:flex; align-items:center; gap:14px;">
                    <button id="sidebarToggle" class="btn btn-secondary btn-sm" style="display:none;">&#9776;</button>
                    <div class="page-title">@yield('title', 'Dashboard')</div>
                </div>
                <div class="user-info">
                    <span class="badge-role">{{ auth()->user()->role === 'super_admin' ? 'Super Admin' : 'User' }}</span>
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Logout</button>
                    </form>
                </div>
            </header>

            <main class="content">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please fix the following:</strong>
                        <ul style="margin:8px 0 0; padding-left:18px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>window.LAST_PRICE_URL = "{{ route('quotations.last-price') }}";</script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
