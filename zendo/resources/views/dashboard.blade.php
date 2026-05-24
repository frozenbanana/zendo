<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — {{ tenant()->name ?? 'Zendo' }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        h1 { color: #4338ca; }
        .role { display: inline-block; background: #f3f4f6; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; margin-left: 0.5rem; }
        .role.admin { background: #dcfce7; color: #166534; }
        .role.editor { background: #dbeafe; color: #1e40af; }
        .role.viewer { background: #f3f4f6; color: #6b7280; }
        a { color: #4338ca; }
    </style>
</head>
<body>
    <h1>{{ tenant()->name ?? 'Zendo' }} Dashboard</h1>

    @auth
        <p>
            Welcome, {{ auth()->user()->name }}
            @if($role = auth()->user()->roleInCurrentTenant())
                <span class="role {{ strtolower($role) }}">{{ $role }}</span>
            @endif
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    @else
        <p><a href="{{ route('login') }}">Log in</a></p>
    @endauth
</body>
</html>
