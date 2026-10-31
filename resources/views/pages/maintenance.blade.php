<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance · {{ setting('site_name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh items-center justify-center p-6">
    <div class="max-w-md text-center">
        <x-logo class="mx-auto size-14" />
        <h1 class="mt-4 text-2xl font-extrabold">{{ setting('site_name') }} revient bientôt</h1>
        <p class="mt-2 whitespace-pre-line text-ink-500">{{ setting('maintenance_message') }}</p>
        <p class="mt-6 text-sm text-ink-400">Urgence ?
            @foreach ((array) setting('emergency_numbers') as $number => $label) {{ $label }} {{ $number }}@unless ($loop->last) · @endunless @endforeach
        </p>
        <a href="{{ route('login') }}" class="mt-6 inline-block text-xs text-ink-300">Accès administrateur</a>
    </div>
</body>
</html>
