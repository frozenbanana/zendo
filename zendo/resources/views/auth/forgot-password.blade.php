<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — {{ config('app.name') }}</title>
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
    <h1>Forgot Password</h1>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <button type="submit">Send Reset Link</button>
    </form>

    <div class="links">
        <a href="{{ route('login') }}">Back to sign in</a>
    </div>
</body>
</html>