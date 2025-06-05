<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Pulse - Authentication</title>
    <link rel="stylesheet" href="{{ asset('vendor/pulse/app.css') }}">
</head>
<body class="font-sans antialiased h-full bg-neutral-900">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-white">Laravel Pulse</h2>
                <p class="mt-2 text-center text-sm text-neutral-300">
                    Please enter the password to access the dashboard
                </p>
            </div>
            <form class="mt-8 space-y-6" action="{{ url(config('pulse.path')) }}" method="GET">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" required class="appearance-none rounded-md relative block w-full px-3 py-2 border border-neutral-700 bg-neutral-800 text-neutral-200 placeholder-neutral-400 focus:outline-none focus:ring-neutral-500 focus:border-neutral-500 focus:z-10 sm:text-sm" placeholder="Password">
                    </div>
                </div>
                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-neutral-600 hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-neutral-500">
                        Access Dashboard
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
