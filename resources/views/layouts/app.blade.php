<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Staff Time Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Public Sans"', 'system-ui', 'sans-serif'] },
                    colors: {
                        ink: '#14181d',
                        muted: '#5a6472',
                        canvas: '#eceeea',
                        line: '#d8dcd6',
                        moss: '#1f6f4a',
                        clay: '#b4691b',
                        brick: '#9d2c2c',
                    },
                },
            },
        }
    </script>
    <style>
        .tnum { font-variant-numeric: tabular-nums; }
        [x-cloak] { display: none; }
    </style>
</head>
<body class="h-full bg-canvas text-ink font-sans antialiased">
<div class="min-h-full lg:flex">

    <aside class="lg:w-60 lg:flex-none bg-ink text-canvas lg:min-h-screen">
        <div class="px-5 py-5 flex items-center justify-between lg:block">
            <div>
                <p class="text-base font-bold leading-tight">Staff Time Records</p>
                <p class="text-xs text-canvas/60 tnum">{{ now()->format('D, d M Y') }}</p>
            </div>
        </div>

        @php
            $nav = [
                ['route' => 'dashboard', 'label' => 'Today', 'active' => request()->routeIs('dashboard')],
                ['route' => 'employees.index', 'label' => 'Employees', 'active' => request()->routeIs('employees.*')],
                ['route' => 'attendance.index', 'label' => 'Time records', 'active' => request()->routeIs('attendance.*')],
            ];
        @endphp

        <nav class="px-3 pb-4 flex lg:block gap-1 overflow-x-auto">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="block whitespace-nowrap rounded px-3 py-2 text-sm transition
                          {{ $item['active'] ? 'bg-canvas text-ink font-semibold' : 'text-canvas/75 hover:bg-white/10' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="px-5 py-4 border-t border-white/10 lg:mt-auto">
            <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
            <p class="text-xs text-canvas/55 mb-2">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs text-canvas/75 underline underline-offset-2 hover:text-white">
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 min-w-0">
        <div class="mx-auto max-w-6xl px-5 py-8">

            <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">@yield('heading', 'Dashboard')</h1>
                    @hasSection('subheading')
                        <p class="text-sm text-muted mt-1">@yield('subheading')</p>
                    @endif
                </div>
                <div>@yield('actions')</div>
            </header>

            @if (session('status'))
                <div class="mb-5 rounded border border-moss/30 bg-moss/10 px-4 py-3 text-sm text-moss">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded border border-brick/30 bg-brick/10 px-4 py-3 text-sm text-brick">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded border border-brick/30 bg-brick/10 px-4 py-3 text-sm text-brick">
                    <p class="font-semibold mb-1">Check the highlighted fields.</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
