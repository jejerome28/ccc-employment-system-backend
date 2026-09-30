<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Staff Time Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: { sans: ['"Public Sans"', 'system-ui', 'sans-serif'] },
                colors: { ink: '#14181d', muted: '#5a6472', canvas: '#eceeea', line: '#d8dcd6', brick: '#9d2c2c' },
            } },
        }
    </script>
</head>
<body class="h-full bg-ink text-canvas font-sans antialiased flex items-center justify-center px-5">
    <div class="w-full max-w-sm">
        <h1 class="text-3xl font-bold tracking-tight">Staff Time Records</h1>
        <p class="mt-1 text-sm text-canvas/60">Sign in to manage employees and daily time in and time out.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-8 bg-canvas text-ink rounded-lg p-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded border border-line bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded border border-line bg-white px-3 py-2 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink">
            </div>

            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" value="1" class="rounded border-line">
                Keep me signed in
            </label>

            @if ($errors->any())
                <p class="text-sm text-brick">{{ $errors->first() }}</p>
            @endif

            <button type="submit"
                    class="w-full rounded bg-ink px-4 py-2.5 text-sm font-semibold text-canvas hover:bg-ink/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ink">
                Sign in
            </button>
        </form>

        <p class="mt-4 text-xs text-canvas/50">
            Accounts are issued by the administrator. There is no self sign-up.
        </p>
    </div>
</body>
</html>
