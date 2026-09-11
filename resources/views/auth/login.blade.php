<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
    <title>Ferosa Landscaping Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <form method="POST" action="{{ route('login.submit') }}" class="bg-white p-8 rounded-xl shadow w-full max-w-md">
        @csrf
        <h1 class="text-2xl font-bold mb-6">Sign In</h1>
        <div class="mb-4">
            <label class="block text-sm mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded p-2" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm mb-1">Password</label>
            <input type="password" name="password" class="w-full border rounded p-2" required>
        </div>
        @error('email')
            <p class="text-sm text-red-600 mb-3">{{ $message }}</p>
        @enderror
        <button class="w-full bg-green-600 text-white rounded p-2 font-semibold">Login</button>
        <p class="mt-4 text-sm">No account? <a class="text-green-700" href="{{ route('register') }}">Register</a></p>
    </form>
</body>
</html>
