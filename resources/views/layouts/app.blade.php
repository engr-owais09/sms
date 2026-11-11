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
<body class="h-full text-slate-800 antialiased flex flex-col justify-between">
    <!-- Top Global Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="/" class="flex items-center gap-2">
                    <span class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        E
                    </span>
                    <span class="font-bold text-xl text-slate-900 tracking-tight">EduCore</span>
                </a>
                @if(isset($tenant))
                    <span class="text-slate-300">/</span>
                    <span class="text-sm font-medium px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $tenant->name }} ({{ ucfirst($tenant->type) }})
                    </span>
                @endif
            </div>

            <!-- Language & Profile Controls -->
            <div class="flex items-center gap-4">
                <!-- Bilingual Switcher -->
                <div class="flex items-center bg-slate-100 rounded-lg p-1 text-xs font-semibold">
                    <a href="?lang=en" class="px-2.5 py-1 rounded {{ ($currentLocale ?? 'en') === 'en' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">
                        English
                    </a>
                    <a href="?lang=ur" class="px-2.5 py-1 rounded {{ ($currentLocale ?? 'en') === 'ur' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-900' }}">
                        اردو
                    </a>
                </div>

                @auth
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <span>{{ auth()->user()->name }}</span>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Global Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>&copy; {{ date('Y') }} EduCore SaaS. {{ ($currentLocale ?? 'en') === 'ur' ? 'تعلیمی اداروں اور اکیڈمیوں کے انتظام کا پلیٹ فارم' : 'Multi-Tenant School & Academy Management Platform.' }}</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
