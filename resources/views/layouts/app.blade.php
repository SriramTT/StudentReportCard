<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School Examination Marks and Report Card Management System">
    <title>@yield('title', 'School Report Card System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar">
            <div class="app-sidebar-header">
                <h2>School System</h2>
            </div>
            <nav class="app-sidebar-nav">
                <div class="nav-section-title">Core</div>
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    Dashboard
                </a>

                @if(auth()->user()->isAdmin() || auth()->user()->isOfficeStaff())
                    <div class="nav-section-title">Academic Setup</div>
                    <a href="{{ route('academic_years.index') }}" class="nav-link {{ request()->routeIs('academic_years.*') ? 'active' : '' }}">
                        Academic Years
                    </a>
                    <a href="{{ route('terms.index') }}" class="nav-link {{ request()->routeIs('terms.*') ? 'active' : '' }}">
                        Terms
                    </a>
                    <a href="{{ route('classes.index') }}" class="nav-link {{ request()->routeIs('classes.*') ? 'active' : '' }}">
                        Classes
                    </a>
                    <a href="{{ route('sections.index') }}" class="nav-link {{ request()->routeIs('sections.*') ? 'active' : '' }}">
                        Sections
                    </a>
                    <a href="{{ route('subjects.index') }}" class="nav-link {{ request()->routeIs('subjects.*') ? 'active' : '' }}">
                        Subjects
                    </a>
                    <a href="{{ route('class_subjects.index') }}" class="nav-link {{ request()->routeIs('class_subjects.*') ? 'active' : '' }}">
                        Class Subjects
                    </a>

                    <div class="nav-section-title">Assessments</div>
                    <a href="{{ route('assessments.types.index') }}" class="nav-link {{ request()->routeIs('assessments.types.*') ? 'active' : '' }}">
                        Assessment Types
                    </a>
                    <a href="{{ route('assessments.index') }}" class="nav-link {{ request()->routeIs('assessments.index') || request()->routeIs('assessments.applicability.*') ? 'active' : '' }}">
                        Assessments
                    </a>

                    <div class="nav-section-title">Calculations & Reports</div>
                    <a href="{{ route('calculations.settings.index') }}" class="nav-link {{ request()->routeIs('calculations.settings.*') ? 'active' : '' }}">
                        Calculation Settings
                    </a>
                    <a href="{{ route('reports.configurations.index') }}" class="nav-link {{ request()->routeIs('reports.configurations.*') ? 'active' : '' }}">
                        Report Configurations
                    </a>
                @endif

                @if(auth()->user()->isAdmin())
                    <div class="nav-section-title">Administration</div>
                    <a href="{{ route('admin.school_settings.edit') }}" class="nav-link {{ request()->routeIs('admin.school_settings.*') ? 'active' : '' }}">
                        School Settings
                    </a>
                @endif
            </nav>
        </aside>

        <!-- Main Content Area -->
        <div class="app-main">
            <!-- Topbar -->
            <header class="app-topbar">
                <div class="app-topbar-title">
                    @yield('page_title', 'Dashboard')
                </div>
                <div class="app-topbar-user">
                    <span style="font-size: var(--font-size-sm); color: var(--color-text-main); font-weight: 600;">
                        {{ auth()->user()->display_name }}
                    </span>
                    <span class="badge badge-primary">
                        {{ auth()->user()->role?->name ?? 'Staff' }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm" id="topbar-logout-btn">
                            Sign Out
                        </button>
                    </form>
                </div>
            </header>

            <!-- Page Body -->
            <main class="app-content">
                @if(session('success'))
                    <div class="alert alert-success" role="alert">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>Please correct the following errors:</strong>
                        <ul style="margin-top: 0.5rem; padding-left: 1.25rem;">
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
</body>
</html>
