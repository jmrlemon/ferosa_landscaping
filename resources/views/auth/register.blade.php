<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
    <title>Ferosa Landscaping Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <form method="POST" action="{{ route('register.submit') }}" class="bg-white p-8 rounded-xl shadow w-full max-w-md">
        @csrf
        <h1 class="text-2xl font-bold mb-6">Create Account</h1>
        <div class="mb-3">
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Full Name" class="w-full border rounded p-2" required>
        </div>
        <div class="mb-3">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" class="w-full border rounded p-2" required>
        </div>
        <div class="mb-3">
            <input type="password" name="password" placeholder="Password" class="w-full border rounded p-2" required>
        </div>
        <div class="mb-4">
            <input type="password" name="password_confirmation" placeholder="Confirm Password" class="w-full border rounded p-2" required>
        </div>
        <button class="w-full bg-green-600 text-white rounded p-2 font-semibold">Register</button>
        <p class="mt-4 text-sm">Have account? <a class="text-green-700" href="{{ route('login') }}">Login</a></p>
    </form>
</body>
</html>
