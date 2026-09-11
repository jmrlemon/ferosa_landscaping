<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
    <title>{{ $title ?? 'Ferosa Landscaping Services' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>

  <script>
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>
</head>
<body class="flex h-screen bg-gray-200 text-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-100 shadow-md flex flex-col justify-between flex-shrink-0">
        <div>
            <!-- Header -->
            <div class="px-6 py-4 bg-gray-600 text-white flex items-center justify-between">
                <a href="{{ route('home') }}" class="font-semibold text-lg tracking-wide hover:text-gray-200">
                    Ferosa Landscaping
                </a>
                <span class="text-gray-300 text-sm font-light cursor-pointer hover:text-white">&times;</span>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex flex-col w-full">
                <a href="{{ route('home') }}" class="block w-full px-6 py-3 text-sm hover:bg-gray-200 {{ request()->routeIs('home') ? 'bg-[#4caf50] text-white font-medium hover:bg-[#4caf50]' : 'text-gray-800' }}">Home</a>
                <a href="{{ route('shop') }}" class="block w-full px-6 py-3 text-sm hover:bg-gray-200 {{ request()->routeIs('shop') ? 'bg-[#4caf50] text-white font-medium hover:bg-[#4caf50]' : 'text-gray-800' }}">Shop</a>
                <a href="{{ route('orders') }}" class="block w-full px-6 py-3 text-sm hover:bg-gray-200 {{ request()->routeIs('orders') ? 'bg-[#4caf50] text-white font-medium hover:bg-[#4caf50]' : 'text-gray-800' }}">Orders</a>
                <a href="{{ route('schedule') }}" class="block w-full px-6 py-3 text-sm hover:bg-gray-200 {{ request()->routeIs('schedule') ? 'bg-[#4caf50] text-white font-medium hover:bg-[#4caf50]' : 'text-gray-800' }}">Schedule</a>
                <a href="{{ route('estimator') }}" class="block w-full px-6 py-3 text-sm hover:bg-gray-200 {{ request()->routeIs('estimator') ? 'bg-[#4caf50] text-white font-medium hover:bg-[#4caf50]' : 'text-gray-800' }}">Estimator</a>
                <a href="{{ route('account') }}" class="block w-full px-6 py-3 text-sm hover:bg-gray-200 {{ request()->routeIs('account') ? 'bg-[#4caf50] text-white font-medium hover:bg-[#4caf50]' : 'text-gray-800' }}">Account</a>
            </nav>
        </div>

        <!-- Bottom Layout -->
        <div class="p-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-200 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto w-full">
        <main class="max-w-6xl p-6">
            @if (session('status'))
                <div class="mb-4 rounded bg-green-50 border border-green-200 text-green-700 p-3">
                    {{ session('status') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
