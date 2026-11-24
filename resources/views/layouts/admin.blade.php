<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - {{ setting('company_name', 'Home Nursing') }}</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Admin CSS -->
    <style>
        :root {
            --sidebar-width: 250px;
        }
        
        body {
            font-size: 0.9rem;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #2c3e50;
            color: white;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .notification-bell {
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        
        .table-orange {
            background-color: #fff3cd !important;
        }
        
        .badge.bg-orange {
            background-color: #fd7e14 !important;
        }
        
        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border: none;
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-3 border-bottom">
            <h5 class="mb-0">{{ setting('company_name', 'Home Nursing') }}</h5>
            <small>Admin Panel</small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link {{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                <i class="fas fa-user-shield me-2"></i> Users
            </a>
            <a class="nav-link {{ request()->routeIs('admin.patients*') ? 'active' : '' }}" href="{{ route('admin.patients.index') }}">
                <i class="fas fa-user-injured me-2"></i> Patients
            </a>
            <a class="nav-link {{ request()->routeIs('admin.employees*') ? 'active' : '' }}" href="{{ route('admin.employees.index') }}">
                <i class="fas fa-user-nurse me-2"></i> Employees
            </a>
            <a class="nav-link {{ request()->routeIs('admin.contracts*') ? 'active' : '' }}" href="{{ route('admin.contracts.index') }}">
                <i class="fas fa-file-contract me-2"></i> Contracts
            </a>
            <a class="nav-link {{ request()->routeIs('admin.attendance*') ? 'active' : '' }}" href="{{ route('admin.attendance.index') }}">
                <i class="fas fa-calendar-check me-2"></i> Attendance
            </a>
            <a class="nav-link {{ request()->routeIs('admin.payouts*') ? 'active' : '' }}" href="{{ route('admin.payouts.index') }}">
                <i class="fas fa-money-bill-wave me-2"></i> Payroll
            </a>
            <a class="nav-link {{ request()->routeIs('admin.payments*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">
                <i class="fas fa-credit-card me-2"></i> Payments
            </a>
            <a class="nav-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <hr class="bg-white">
            <small class="px-3 text-white-50">CMS</small>
            <a class="nav-link {{ request()->routeIs('admin.banners*') ? 'active' : '' }}" href="{{ route('admin.banners.index') }}">
                <i class="fas fa-image me-2"></i> Banners
            </a>
            <a class="nav-link {{ request()->routeIs('admin.services*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}">
                <i class="fas fa-briefcase-medical me-2"></i> Services
            </a>
            <a class="nav-link {{ request()->routeIs('admin.blogs*') ? 'active' : '' }}" href="{{ route('admin.blogs.index') }}">
                <i class="fas fa-blog me-2"></i> Blogs
            </a>
            <a class="nav-link {{ request()->routeIs('admin.contact-queries*') ? 'active' : '' }}" href="{{ route('admin.contact-queries.index') }}">
                <i class="fas fa-envelope me-2"></i> Contact Queries
            </a>
            <a class="nav-link {{ request()->routeIs('admin.join-requests*') ? 'active' : '' }}" href="{{ route('admin.join-requests.index') }}">
                <i class="fas fa-user-plus me-2"></i> Join Requests
            </a>
            <hr class="bg-white">
            <a class="nav-link {{ request()->routeIs('admin.castes*') ? 'active' : '' }}" href="{{ route('admin.castes.index') }}">
                <i class="fas fa-users me-2"></i> Castes
            </a>
            <a class="nav-link {{ request()->routeIs('admin.employee-fields*') ? 'active' : '' }}" href="{{ route('admin.employee-fields.index') }}">
                <i class="fas fa-cog me-2"></i> Employee Fields
            </a>
            <a class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
                <i class="fas fa-sliders-h me-2"></i> Settings
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">@yield('page-title', 'Dashboard')</h4>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notifications -->
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell fa-lg"></i>
                        <span class="notification-badge" id="notificationCount">0</span>
                    </div>
                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn btn-link text-decoration-none dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle fa-lg me-1"></i>
                            {{ auth()->user()->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('home') }}" target="_blank"><i class="fas fa-globe me-2"></i>View Website</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('admin.logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="container-fluid px-4 pb-4">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <!-- jQuery (for compatibility) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Form Validation Library -->
    <script src="{{ asset('js/form-validation.js') }}"></script>
    <!-- Admin AJAX Form Handler -->
    <script src="{{ asset('js/admin-forms.js') }}"></script>
    <script>
        // Load notification count
        // This would be implemented with AJAX to fetch real-time notifications
    </script>
    @stack('scripts')
</body>
</html>

