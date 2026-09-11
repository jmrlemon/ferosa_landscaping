{{-- Shared shell for every error page. Deliberately standalone rather than
     extending layouts.customer: an error can fire before or outside a session,
     and the customer layout runs view composers that query the database. An
     error page must render when the database is the thing that broke. --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
<title>@yield('code') · Ferosa Landscaping</title>
<link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: #f8f7f3;
    color: #183127;
    font-family: 'DM Sans', system-ui, sans-serif;
    -webkit-text-size-adjust: 100%;
  }
  .card {
    width: 100%;
    max-width: 30rem;
    padding: 2.5rem 2rem;
    border: 1px solid #eae7df;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(18,52,38,.03), 0 18px 44px rgba(18,52,38,.06);
    text-align: center;
  }
  .mark {
    width: 3rem;
    height: 3rem;
    margin: 0 auto 1.25rem;
    border-radius: 14px;
    background: #123426;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .code {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #236746;
  }
  h1 {
    margin-top: .5rem;
    font-family: 'Fraunces', Georgia, serif;
    font-size: clamp(1.5rem, 1.2rem + 1.4vw, 2rem);
    font-weight: 700;
    letter-spacing: -.025em;
    line-height: 1.15;
  }
  p { margin-top: .75rem; font-size: .9375rem; line-height: 1.65; color: #706b61; }
  .actions { margin-top: 1.75rem; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; }
  a.btn {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 0 1.15rem;
    border-radius: 12px;
    font-size: .875rem;
    font-weight: 700;
    text-decoration: none;
    transition: background .18s ease;
  }
  a.primary { background: #1b5239; color: #fff; }
  a.primary:hover { background: #123426; }
  a.ghost { background: #eef7f1; color: #1b5239; }
  a.ghost:hover { background: #dcefe3; }
</style>
</head>
<body>
  <main class="card">
    <div class="mark">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3C12 3 7 6.5 7 12c0 2.76 1.34 5.22 3.4 6.74.38-.48.93-.74 1.6-.74s1.22.26 1.6.74C15.66 17.22 17 14.76 17 12c0-5.5-5-9-5-9z" fill="#fff"/>
      </svg>
    </div>
    <p class="code">Error @yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
      <a class="btn primary" href="{{ url('/') }}">Go to Ferosa</a>
      <a class="btn ghost" href="{{ url('/shop') }}">Browse the shop</a>
    </div>
  </main>
</body>
</html>
