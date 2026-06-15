<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DropFlow SFM')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    @php
        $modules = config('dropflow.modules', []);
    @endphp
    <div class="flex min-h-screen">
        <aside class="w-64 shrink-0 bg-slate-900 text-slate-300 flex flex-col">
            <div class="px-5 py-6 border-b border-slate-700">
                <a href="{{ route('dashboard') }}" class="block">
                    <span class="text-lg font-semibold text-white tracking-tight">DropFlow SFM</span>
                    <span class="block text-xs text-slate-400 mt-1">Lokkisona · Ex-A</span>
                </a>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1 text-sm overflow-y-auto">
                <a href="{{ route('dashboard') }}"
                   class="block rounded-md px-3 py-2 hover:bg-slate-800 hover:text-white {{ request()->routeIs('dashboard') ? 'sidebar-link-active' : '' }}">
                    Dashboard
                </a>

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('connection.edit') }}"
                       class="block rounded-md px-3 py-2 hover:bg-slate-800 hover:text-white {{ request()->routeIs('connection.*') ? 'sidebar-link-active' : '' }}">
                        Connection
                        @if ($modules['connection'] ?? false)
                            <span class="ml-1 text-[10px] uppercase text-emerald-400">Step 1</span>
                        @endif
                    </a>

                    @if ($modules['product_map'] ?? false)
                        <a href="{{ route('product-map.index') }}"
                           class="block rounded-md px-3 py-2 hover:bg-slate-800 hover:text-white {{ request()->routeIs('product-map.*') ? 'sidebar-link-active' : '' }}">
                            Product Map
                            <span class="ml-1 text-[10px] uppercase text-sky-400">Step 2A</span>
                        </a>
                    @else
                        <span class="block rounded-md px-3 py-2 text-slate-500 cursor-not-allowed" title="Enabled after Step 1 approval">
                            Product Map <span class="text-[10px] uppercase">Soon</span>
                        </span>
                    @endif

                    @if ($modules['order_map'] ?? false)
                        <a href="{{ route('order-map.index') }}"
                           class="block rounded-md px-3 py-2 hover:bg-slate-800 hover:text-white {{ request()->routeIs('order-map.*') ? 'sidebar-link-active' : '' }}">
                            Order Map
                        </a>
                    @else
                        <span class="block rounded-md px-3 py-2 text-slate-500 cursor-not-allowed" title="Enabled after Step 2 approval">
                            Order Map <span class="text-[10px] uppercase">Soon</span>
                        </span>
                    @endif
                @endif
            </nav>

            <div class="px-5 py-4 border-t border-slate-700 text-xs">
                <p class="text-slate-400 truncate">{{ auth()->user()->name }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="text-slate-400 hover:text-white">Sign out</button>
                </form>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-white border-b border-slate-200 px-6 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-900">@yield('page-title', 'Dashboard')</h1>
                        @hasSection('page-subtitle')
                            <p class="text-sm text-slate-500 mt-1">@yield('page-subtitle')</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0 page-header-actions">
                        @hasSection('page-actions')
                            @yield('page-actions')
                        @endif
                        <x-logs-drawer :drawer="$logsDrawer ?? []" />
                        @hasSection('page-badge')
                            @yield('page-badge')
                        @endif
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6">
                @if (session('success'))
                    <div class="mb-4 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if (session('info'))
                    <div class="mb-4 rounded-md bg-sky-50 border border-sky-200 px-4 py-3 text-sm text-sky-800">
                        {{ session('info') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
