<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
        h1 { color: #4338ca; }
        label { display: block; margin-top: 1rem; font-weight: 500; }
        input { width: 100%; padding: 0.5rem; margin-top: 0.25rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
        button { margin-top: 1.5rem; width: 100%; padding: 0.75rem; background: #4338ca; color: white; border: none; border-radius: 0.375rem; font-size: 1rem; cursor: pointer; }
        button:hover { background: #3730a3; }
        .error { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
        .links { margin-top: 1rem; font-size: 0.875rem; }
        .links a { color: #4338ca; }
    </style>
</head>
<body>
    <h1>Create your account</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <label for="name">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <button type="submit">Register</button>
    </form>

    <div class="links">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </div>
</body>
</html>