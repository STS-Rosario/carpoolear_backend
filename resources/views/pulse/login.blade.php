<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pulse Login - {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f5f5;
            color: #111827;
        }

        .card {
            width: min(100%, 24rem);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }

        p {
            margin: 0 0 1.5rem;
            color: #6b7280;
        }

        label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 1rem;
            padding: 0.75rem 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font: inherit;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            background: #111827;
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .error {
            margin-bottom: 1rem;
            padding: 0.75rem 0.875rem;
            border-radius: 0.5rem;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Pulse</h1>
        <p>Admin access to application monitoring.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('pulse.login') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
