<link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
<style id="ferosa-admin-premium-theme">
  :root {
    --admin-forest-950: #081d15;
    --admin-forest-900: #123426;
    --admin-forest-800: #17422f;
    --admin-forest-700: #1b5239;
    --admin-forest-600: #236746;
    --admin-sage-100: #d8ecdf;
    --admin-sage-50: #eef7f1;
    --admin-stone-50: #f8f7f3;
    --admin-stone-100: #f0eee8;
    --admin-stone-200: #e2ded4;
    --admin-ink: #181714;
  }

  html { background: var(--admin-stone-50); }
  body {
    background:
      radial-gradient(circle at 92% 0%, rgba(130, 189, 152, .12), transparent 26rem),
      var(--admin-stone-50) !important;
    color: var(--admin-ink) !important;
    font-family: 'DM Sans', sans-serif !important;
  }
  body > header {
    height: 4.25rem !important;
    border-color: var(--admin-stone-200) !important;
    background: rgba(255,255,255,.9) !important;
    backdrop-filter: blur(18px);
  }
  body > header h1 {
    color: var(--admin-forest-800) !important;
    font-size: .7rem !important;
    font-weight: 700 !important;
    letter-spacing: .13em;
    text-transform: uppercase;
  }
  body > main {
    width: min(100%, 94rem);
    margin-inline: auto;
    padding: 1.5rem !important;
  }
  body > main h2 {
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 600 !important;
    letter-spacing: -.02em;
  }
  #admin-sidebar { background: linear-gradient(180deg, rgba(238,247,241,.78), transparent 190px), #fff; }

  /* The scroll container is `#tabs-main` on the dashboard and
     `#admin-workspace-main` on every standalone admin page. Both get the same
     surface so switching between them does not change the page chrome. */
  #tabs-main,
  #admin-workspace-main {
    background:
      radial-gradient(circle at 90% 0%, rgba(130,189,152,.10), transparent 24rem),
      var(--admin-stone-50);
  }
  #tabs-main > main,
  #admin-workspace-main > main {
    width: min(100%, 100rem);
    margin-inline: auto;
  }
  #tabs-main .overflow-x-auto,
  #admin-workspace-main .overflow-x-auto { scrollbar-gutter: stable; }
  #tabs-main table thead,
  #admin-workspace-main table thead { background: rgba(248,247,243,.86); }
  #tabs-main h2,
  #admin-workspace-main h2 { letter-spacing: -.01em; }

  section.rounded-xl,
  div.rounded-xl.bg-white,
  form.rounded-xl.bg-white {
    border-color: #e8e4db !important;
    border-radius: 1rem !important;
    box-shadow: 0 1px 2px rgba(18,52,38,.025), 0 12px 36px rgba(18,52,38,.045) !important;
  }
  input:not([type="checkbox"]):not([type="radio"]), select, textarea {
    min-height: 2.75rem;
    border-color: var(--admin-stone-200) !important;
    border-radius: .75rem !important;
    background: #fff;
  }
  input:focus, select:focus, textarea:focus {
    border-color: var(--admin-forest-600) !important;
    box-shadow: 0 0 0 3px rgba(52,127,87,.12) !important;
  }
  button, a { transition-duration: .16s !important; }
  .bg-brand-50 { background-color: var(--admin-sage-50) !important; }
  .bg-brand-100 { background-color: var(--admin-sage-100) !important; }
  .bg-brand-600 { background-color: var(--admin-forest-600) !important; }
  .bg-brand-700 { background-color: var(--admin-forest-700) !important; }
  .bg-brand-800, .bg-brand-950 { background-color: var(--admin-forest-950) !important; }
  .text-brand-600 { color: var(--admin-forest-600) !important; }
  .text-brand-700, .text-brand-800 { color: var(--admin-forest-700) !important; }
  .text-brand-950 { color: var(--admin-forest-950) !important; }
  .border-brand-100 { border-color: var(--admin-sage-100) !important; }
  .border-brand-600 { border-color: var(--admin-forest-600) !important; }
  .bg-surface-50 { background-color: var(--admin-stone-50) !important; }
  .bg-surface-100 { background-color: var(--admin-stone-100) !important; }
  .border-surface-100, .border-surface-200, .border-surface-300 { border-color: var(--admin-stone-200) !important; }
  .hover\:bg-brand-700:hover { background-color: var(--admin-forest-700) !important; }

  @media (max-width: 639px) {
    body > header { padding-inline: 1rem !important; }
    body > header > div > span { display: none; }
    body > main { padding: 1rem !important; }
    body > main h2 { font-size: 1.6rem !important; }
    body > main > .mb-5.flex,
    body > main > .mb-6.flex { align-items: flex-start !important; }
    body > main > .mb-5.flex > a,
    body > main > .mb-6.flex > div > a { margin-top: .2rem; }
    section.rounded-xl { border-radius: .875rem !important; }
  }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
  }
</style>
@include('admin.partials.type-scale')
@include('partials.a11y-focus')
@include('partials.scrollbars')
