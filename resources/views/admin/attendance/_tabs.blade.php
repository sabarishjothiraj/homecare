<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1">Attendance</h2>
        <p class="text-muted mb-0">Mark daily attendance; payroll is calculated from it</p>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.attendance.index') ? 'active' : '' }}" href="{{ route('admin.attendance.index') }}">
                <i class="fas fa-calendar-day me-1"></i>Daily Sheet
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.attendance.register') ? 'active' : '' }}" href="{{ route('admin.attendance.register') }}">
                <i class="fas fa-th me-1"></i>Monthly Register
            </a>
        </li>
    </ul>
</div>
