<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Blastr Admin - Login</title>
    <link rel="icon" href="/images/logo.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0e0e10;
            color: #e0e0e0;
            font-family: 'Manrope', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        .login-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-title span { color: #ef4444; }
        .login-subtitle {
            color: #888;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }
        .login-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            color: #e0e0e0;
            font-size: 0.875rem;
            font-family: 'Manrope', sans-serif;
            margin-bottom: 1rem;
        }
        .login-input:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }
        .login-btn {
            width: 100%;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: 'Manrope', sans-serif;
            cursor: pointer;
            transition: background 0.15s;
        }
        .login-btn:hover { background: #dc2626; }
        .login-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            color: #f87171;
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-title"><span>Blastr</span> Admin</div>
        <div class="login-subtitle">Enter your access key to continue</div>

        @if($errors->any())
            <div class="login-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/login">
            @csrf
            <input type="password" name="access_key" class="login-input" placeholder="Access Key" autofocus required>
            <button type="submit" class="login-btn">Authenticate</button>
        </form>
    </div>
</body>
</html>
