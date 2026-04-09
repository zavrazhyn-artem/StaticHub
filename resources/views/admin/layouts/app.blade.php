<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Blastr Admin</title>
    <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css'])

    <style>
        body { background: #0e0e10; color: #e0e0e0; font-family: 'Manrope', sans-serif; }
        .admin-primary { color: #ef4444; }
        .admin-primary-bg { background-color: #ef4444; }
        .admin-primary-bg:hover { background-color: #dc2626; }
        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
        }
        .admin-sidebar {
            background: rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            color: #a0a0a0;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.15s;
        }
        .admin-nav-link:hover, .admin-nav-link.active {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #888;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .admin-table td {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .admin-table tr:hover td {
            background: rgba(255,255,255,0.02);
        }
        .admin-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .admin-badge-success { background: rgba(34,197,94,0.15); color: #4ade80; }
        .admin-badge-error { background: rgba(239,68,68,0.15); color: #f87171; }
        .admin-badge-neutral { background: rgba(255,255,255,0.08); color: #a0a0a0; }
        .admin-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            color: #e0e0e0;
            font-size: 0.875rem;
        }
        .admin-input:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239,68,68,0.2);
        }
        .admin-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
        }
        .admin-btn-primary {
            background: #ef4444;
            color: white;
        }
        .admin-btn-primary:hover { background: #dc2626; }
        .admin-btn-danger {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.2);
        }
        .admin-btn-danger:hover { background: rgba(239,68,68,0.2); }
        .admin-btn-ghost {
            background: rgba(255,255,255,0.05);
            color: #a0a0a0;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .admin-btn-ghost:hover { background: rgba(255,255,255,0.1); color: #e0e0e0; }
        .admin-metric-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }
        .admin-metric-label {
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body class="antialiased">
    <div style="display: flex; min-height: 100vh;">
        {{-- Sidebar --}}
        <aside class="admin-sidebar" style="width: 250px; padding: 1.5rem 1rem; flex-shrink: 0;">
            <div style="margin-bottom: 2rem; padding: 0 0.5rem;">
                <span style="font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 1.25rem;">
                    <span class="admin-primary">Blastr</span> Admin
                </span>
            </div>

            <nav style="display: flex; flex-direction: column; gap: 0.25rem;">
                <a href="{{ route('admin.dashboard') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="material-symbols-outlined" style="font-size: 20px;">dashboard</span>
                    Dashboard
                </a>
                <a href="{{ route('admin.ai-logs') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.ai-logs') ? 'active' : '' }}">
                    <span class="material-symbols-outlined" style="font-size: 20px;">psychology</span>
                    AI Logs
                </a>
                <a href="{{ route('admin.api-logs') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.api-logs') ? 'active' : '' }}">
                    <span class="material-symbols-outlined" style="font-size: 20px;">api</span>
                    API Logs
                </a>
                <a href="{{ route('admin.invite-codes') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.invite-codes') ? 'active' : '' }}">
                    <span class="material-symbols-outlined" style="font-size: 20px;">vpn_key</span>
                    Invite Codes
                </a>

                <div style="margin: 1rem 0; border-top: 1px solid rgba(255,255,255,0.06);"></div>
                <span style="padding: 0 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #555;">External Tools</span>

                {{-- Pulse disabled temporarily due to PHP 8.5 compatibility issue
                <a href="/pulse" target="_blank" class="admin-nav-link">
                    <span class="material-symbols-outlined" style="font-size: 20px;">monitor_heart</span>
                    Pulse
                    <span class="material-symbols-outlined" style="font-size: 14px; margin-left: auto;">open_in_new</span>
                </a>
                --}}
                <a href="/horizon" target="_blank" class="admin-nav-link">
                    <span class="material-symbols-outlined" style="font-size: 20px;">queue</span>
                    Horizon
                    <span class="material-symbols-outlined" style="font-size: 14px; margin-left: auto;">open_in_new</span>
                </a>
                <a href="/log-viewer" target="_blank" class="admin-nav-link">
                    <span class="material-symbols-outlined" style="font-size: 20px;">description</span>
                    Log Viewer
                    <span class="material-symbols-outlined" style="font-size: 14px; margin-left: auto;">open_in_new</span>
                </a>
            </nav>

            <div style="margin-top: auto; padding-top: 2rem;">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="admin-nav-link" style="width: 100%; border: none; background: none; cursor: pointer;">
                        <span class="material-symbols-outlined" style="font-size: 20px;">logout</span>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <main style="flex: 1; padding: 2rem; overflow-x: auto;">
            @if(session('success'))
                <div style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.5rem; color: #4ade80; font-size: 0.875rem;">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.5rem; color: #f87171; font-size: 0.875rem;">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
