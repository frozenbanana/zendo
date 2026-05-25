<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
        h1 { color: #4338ca; }
        label { display: block; margin-top: 1rem; font-weight: 500; }
        input[type="email"], input[type="password"] { width: 100%; padding: 0.5rem; margin-top: 0.25rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
        button { margin-top: 1.5rem; width: 100%; padding: 0.75rem; background: #4338ca; color: white; border: none; border-radius: 0.375rem; font-size: 1rem; cursor: pointer; }
        button:hover { background: #3730a3; }
        .error { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
        .links { margin-top: 1rem; font-size: 0.875rem; }
        .links a { color: #4338ca; }
        .divider { text-align: center; margin: 1.5rem 0 0; position: relative; }
        .divider::before, .divider::after { content: ''; position: absolute; top: 50%; width: 40%; height: 1px; background: #d1d5db; }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        .divider span { background: white; padding: 0 0.5rem; color: #6b7280; font-size: 0.75rem; }
        .google-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; margin-top: 1.5rem; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: white; color: #374151; font-size: 0.875rem; font-weight: 500; text-decoration: none; cursor: pointer; }
        .google-btn:hover { background: #f9fafb; }
        .google-btn svg { width: 1.25rem; height: 1.25rem; }
    </style>
</head>
<body>
    <h1>Sign in to {{ tenant()?->name ?? config('app.name') }}</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <button type="submit">Sign In</button>
    </form>

    <div class="links">
        <a href="{{ route('register') }}">Create an account</a>
        @if(Route::has('password.request'))
            &middot; <a href="{{ route('password.request') }}">Forgot password?</a>
        @endif
    </div>

    <div class="divider"><span>or</span></div>

    <a href="{{ route('auth.google') }}" class="google-btn">
        <svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.3v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.08z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Sign in with Google
    </a>
</body>
</html>
