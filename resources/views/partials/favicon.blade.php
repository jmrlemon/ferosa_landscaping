{{-- Browser and home-screen icons.

     Without these the browser falls back to asking for /favicon.ico on its
     own, which under this deployment is Laravel's default mark rather than
     ours. The SVG is what modern browsers pick and stays crisp at any size;
     the PNG and ICO cover the rest, and the Apple icon is what iOS uses when
     someone saves the site to a home screen. --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
