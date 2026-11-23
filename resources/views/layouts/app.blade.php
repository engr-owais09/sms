<!DOCTYPE html>
<html lang="{{ $currentLocale ?? 'en' }}" dir="{{ $currentDirection ?? 'ltr' }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'EduCore SaaS') }}</title>

    <!-- Google Fonts: Inter for English & Noto Nastaliq Urdu / Noto Sans Arabic for Urdu -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    <style>
        [dir="rtl"] {
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Segoe UI', Tahoma, sans-serif;
            letter-spacing: 0;
            line-height: 1.8;
        }
        [dir="ltr"] {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-slate-800 antialiased flex flex-col justify-between overflow-x-hidden">
    <!-- Top Global Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 min-h-16 py-2 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                @auth
                    <button
                        type="button"
                        id="mobile-menu-toggle"
                        class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        aria-expanded="false"
                        aria-controls="mobile-navigation-drawer"
                        aria-label="{{ __('sms.nav.open_menu') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                @endauth

                <a href="/" class="flex items-center gap-2 shrink-0">
                    <span class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        E
                    </span>
                    <span class="font-bold text-xl text-slate-900 tracking-tight">EduCore</span>
                </a>
                @if(isset($tenant))
                    <span class="hidden sm:inline text-slate-300">/</span>
                    <span class="hidden sm:inline-block truncate max-w-[14rem] text-xs font-medium px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $tenant->name }} ({{ ucfirst($tenant->type) }})
                    </span>
                @endif
            </div>

            <!-- Primary Desktop Navigation -->
            @auth
                <nav aria-label="Primary Navigation" class="hidden md:flex flex-wrap items-center gap-3 lg:gap-4 text-xs font-semibold text-slate-600">
                    @if(isset($tenant))
                        <a href="{{ route('tenant.academics.setup') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.academic_setup') }}</a>
                        <a href="{{ route('tenant.students.index') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.students') }}</a>
                        <a href="{{ route('tenant.finance.terminal') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.finance_pos') }}</a>
                        <a href="{{ route('tenant.finance.operations') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.expenses_refunds') }}</a>
                        <a href="{{ route('tenant.finance.ledger') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.ledger') }}</a>
                        <a href="{{ route('tenant.attendance.register') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.attendance') }}</a>
                        <a href="{{ route('tenant.exams.results') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.exams') }}</a>
                        <a href="{{ route('tenant.staff.index') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.staff_roles') }}</a>
                    @elseif(auth()->user()->isSuperAdmin())
                        <a href="{{ route('super-admin.dashboard') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.tenants') }}</a>
                        <a href="{{ route('super-admin.plans') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.plans') }}</a>
                        <a href="{{ route('super-admin.billing') }}" class="hover:text-indigo-600 whitespace-nowrap">{{ __('sms.nav.billing') }}</a>
                    @endif
                </nav>
            @endauth

            <!-- Language & Profile Controls -->
            <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                <!-- Bilingual Switcher -->
                <div class="flex items-center bg-slate-100 rounded-lg p-1 text-xs font-semibold" role="group" aria-label="{{ __('sms.nav.language') }}">
                    <a href="?lang=en" class="px-2 py-1 rounded {{ ($currentLocale ?? 'en') === 'en' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">
                        English
                    </a>
                    <a href="?lang=ur" class="px-2 py-1 rounded {{ ($currentLocale ?? 'en') === 'ur' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">
                        اردو
                    </a>
                </div>

                @auth
                    <div class="flex items-center gap-2 sm:gap-3 text-xs text-slate-600">
                        <span class="hidden sm:inline font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1 rounded bg-slate-100 hover:bg-rose-50 hover:text-rose-700 font-semibold transition">
                                {{ __('sms.nav.logout') }}
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">
                        {{ __('sms.nav.sign_in') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Accessible Mobile Navigation Drawer -->
    @auth
        <div
            id="mobile-navigation-drawer"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('sms.nav.mobile_menu') }}"
            class="fixed inset-0 z-50 md:hidden hidden"
        >
            <div id="mobile-menu-backdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs" aria-hidden="true"></div>
            <div class="relative flex h-full w-72 max-w-[85vw] flex-col bg-white shadow-xl border-e border-slate-200 p-5 overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                    <div class="min-w-0">
                        <div class="font-bold text-base text-slate-900">EduCore</div>
                        @if(isset($tenant))
                            <div class="text-xs text-indigo-700 font-medium truncate">{{ $tenant->name }}</div>
                        @endif
                    </div>
                    <button
                        type="button"
                        id="mobile-menu-close"
                        class="p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        aria-label="{{ __('sms.nav.close_menu') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-3 border-b border-slate-100 text-xs text-slate-600">
                    <span class="font-semibold text-slate-900">{{ auth()->user()->name }}</span>
                </div>

                <nav aria-label="{{ __('sms.nav.mobile_menu') }}" class="flex-1 py-4 flex flex-col gap-1 text-sm font-semibold text-slate-700">
                    @if(isset($tenant))
                        <a href="{{ route('tenant.academics.setup') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.academic_setup') }}</a>
                        <a href="{{ route('tenant.students.index') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.students') }}</a>
                        <a href="{{ route('tenant.finance.terminal') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.finance_pos') }}</a>
                        <a href="{{ route('tenant.finance.operations') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.expenses_refunds') }}</a>
                        <a href="{{ route('tenant.finance.ledger') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.ledger') }}</a>
                        <a href="{{ route('tenant.attendance.register') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.attendance') }}</a>
                        <a href="{{ route('tenant.exams.results') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.exams') }}</a>
                        <a href="{{ route('tenant.staff.index') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.staff_roles') }}</a>
                    @elseif(auth()->user()->isSuperAdmin())
                        <a href="{{ route('super-admin.dashboard') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.tenants') }}</a>
                        <a href="{{ route('super-admin.plans') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.plans') }}</a>
                        <a href="{{ route('super-admin.billing') }}" data-mobile-nav-link class="px-3 py-2.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-700">{{ __('sms.nav.billing') }}</a>
                    @endif
                </nav>
            </div>
        </div>
    @endauth

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Global Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>&copy; {{ date('Y') }} EduCore SaaS. {{ __('sms.nav.footer_tagline') }}</p>
        </div>
    </footer>

    @auth
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const toggleBtn = document.getElementById('mobile-menu-toggle');
                const drawer = document.getElementById('mobile-navigation-drawer');
                const closeBtn = document.getElementById('mobile-menu-close');
                const backdrop = document.getElementById('mobile-menu-backdrop');

                if (!toggleBtn || !drawer) return;

                function openMenu() {
                    drawer.classList.remove('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    if (closeBtn) closeBtn.focus();
                }

                function closeMenu(returnFocus) {
                    drawer.classList.add('hidden');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    if (returnFocus) toggleBtn.focus();
                }

                toggleBtn.addEventListener('click', function () {
                    const isOpen = !drawer.classList.contains('hidden');
                    if (isOpen) {
                        closeMenu(false);
                    } else {
                        openMenu();
                    }
                });

                if (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        closeMenu(true);
                    });
                }

                if (backdrop) {
                    backdrop.addEventListener('click', function () {
                        closeMenu(true);
                    });
                }

                drawer.querySelectorAll('[data-mobile-nav-link]').forEach(function (link) {
                    link.addEventListener('click', function () {
                        closeMenu(false);
                    });
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !drawer.classList.contains('hidden')) {
                        closeMenu(true);
                    }
                });
            });
        </script>
    @endauth

    @livewireScripts
</body>
</html>
