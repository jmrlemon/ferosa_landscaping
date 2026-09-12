<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  @include('partials.favicon')
<title>Ferosa Landscaping - Garden & Landscaping</title>
<meta name="description" content="Plan landscaping projects, schedule services, shop garden essentials, and track updates with Ferosa Landscaping.">
<meta property="og:type" content="website">
<meta property="og:title" content="Ferosa Landscaping">
<meta property="og:description" content="Plan. Book. Grow beautifully in Orani, Bataan.">
<meta property="og:image" content="{{ asset('og.png') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ asset('og.png') }}">
<link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
{{-- Fonts load async: as a plain stylesheet this blocks first paint on a
     round-trip to Google. Swapping media to "all" once it arrives lets the page
     paint immediately in the fallback face, then upgrade (display=swap). --}}
<link rel="preload" as="image" fetchpriority="high" href="{{ asset('images/ferosa-login-hero.jpg') }}">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --green: #347f57;
  --green-dark: #1b5239;
  --green-deep: #123426;
  --paper: #f8f7f3;
  --ink: #183127;
  /* Viewport height frozen at page load, updated only on a real width change
     or rotation. The Android shell uses adjustResize, so the soft keyboard
     genuinely shrinks the WebView - and every svh/vh-based min-height would
     otherwise recompute mid-typing and shove the card upward. */
  --app-vh: 100svh;
}

html, body {
  /* min-height, not height: with adjustResize a fixed 100% makes body shorter
     than its own content the moment the keyboard opens. */
  min-height: 100%;
  font-family: 'DM Sans', sans-serif;
  overflow-x: hidden;
  /* Allow vertical scroll so keyboard doesn't cover inputs */
  overflow-y: auto;
  -webkit-text-size-adjust: 100%;
  text-size-adjust: 100%;
  /* No scroll-padding-bottom here: it makes the browser's own focus scroll
     overshoot by that much, which is exactly the jump the keyboard-pinning
     script below has to undo. Keyboard clearance is handled in JS instead. */
}

/* ── Fullscreen background ── */
.scene-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  transform: scale(1.06);
  transition: transform 0.1s linear;
  will-change: transform;
  overflow: hidden;
}

.scene-overlay {
  position: fixed;
  inset: 0;
  background: linear-gradient(
    155deg,
    rgba(255,248,240,0.15) 0%,
    rgba(240,255,244,0.10) 40%,
    rgba(10,30,10,0.28) 100%
  );
  z-index: 1;
}

/* ── Brand bottom-left ── */
.brand {
  position: fixed;
  bottom: 38px;
  left: 42px;
  z-index: 20;
  animation: fadeUp 0.9s ease 0.3s both;
}
.brand-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 11px;
}
.brand-icon {
  width: 36px; height: 36px;
  background: var(--green);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(34,197,94,0.5);
  flex-shrink: 0;
}
.brand-name {
  font-family: 'Playfair Display', serif;
  font-size: 23px; font-weight: 700;
  color: white;
  text-shadow: 0 1px 10px rgba(0,0,0,0.35);
  letter-spacing: 0.5px;
}
.brand-slogan {
  font-size: 14.5px; font-weight: 600;
  color: rgba(255,255,255,0.95);
  text-shadow: 0 1px 10px rgba(0,0,0,0.4);
  max-width: 310px; line-height: 1.45;
  margin-bottom: 7px;
}
.brand-sub {
  font-size: 12px;
  color: rgba(255,255,255,0.65);
  text-shadow: 0 1px 8px rgba(0,0,0,0.4);
  max-width: 290px; line-height: 1.6;
}

/* ── Help badge ── */
.help-badge {
  position: fixed;
  bottom: 28px; right: 28px;
  z-index: 20;
  width: 34px; height: 34px;
  border-radius: 50%;
  background: rgba(17,24,39,0.65);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.18);
  color: white;
  font-size: 15px; font-weight: 600;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
}

/* ── Scene wrapper ── */
.scene {
  position: relative;
  z-index: 5;
  min-height: var(--app-vh);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
}

/* ── Mobile overrides ── */
@media (max-width: 600px) {
  .brand { display: none; }
  .help-badge { bottom: 18px; right: 18px; }
  .scene {
    align-items: flex-start;
    padding: 24px 16px 100px;
    min-height: var(--app-vh);
  }
  .form-card {
    padding: 32px 24px 28px;
    border-radius: 20px;
    max-width: 100%;
  }
  .form-title { font-size: 28px; }
}

/* ── Glass form card ── */
.form-card {
  width: 100%;
  max-width: 452px;
  background: rgba(255,255,255,0.74);
  backdrop-filter: blur(24px) saturate(200%);
  -webkit-backdrop-filter: blur(24px) saturate(200%);
  border: 1px solid rgba(255,255,255,0.6);
  border-radius: 28px;
  padding: 44px 40px 38px;
  box-shadow:
    0 10px 50px rgba(0,0,0,0.16),
    0 2px 14px rgba(0,0,0,0.07),
    inset 0 1px 0 rgba(255,255,255,0.85);
}

/* ── Page switch animations ── */
@keyframes fadeUp {
  from { opacity:0; transform:translateY(22px); }
  to   { opacity:1; transform:translateY(0); }
}
@keyframes fadeDown {
  from { opacity:1; transform:translateY(0); }
  to   { opacity:0; transform:translateY(-14px); }
}

.page { display:none; }
.page.active { display:flex; align-items:center; justify-content:center; }
.page.active .form-card { animation: fadeUp 0.55s cubic-bezier(0.22,1,0.36,1) both; }

/* ── Form header ── */
.form-header { text-align:center; margin-bottom:26px; }
.form-title {
  font-family: 'Playfair Display', serif;
  font-size: 36px; font-weight: 700;
  color: #181714; line-height: 1.15;
  margin-bottom: 7px;
}
.form-subtitle { font-size: 14px; color: #4b5563; }

/* ── Fields ── */
.field { margin-bottom:13px; }
.field-label {
  display:block; font-size:12.5px; font-weight:600;
  color:#3b3833; margin-bottom:6px; letter-spacing:0.2px;
}
.field-label-row {
  display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;
}
.forgot-link {
  font-size:12.5px; color:var(--green-dark); font-weight:600;
  cursor:pointer; text-decoration:none;
}
.forgot-link:hover { text-decoration:underline; }

.input-wrap { position:relative; display:flex; align-items:center; }
.input-icon {
  position:absolute; left:13px; color:#a8a196;
  display:flex; align-items:center; pointer-events:none;
}
.input-eye {
  position:absolute; right:13px; color:#a8a196;
  cursor:pointer; display:flex; align-items:center;
}
.field input {
  width:100%; padding:12px 13px 12px 38px;
  border:1.5px solid rgba(0,0,0,0.1);
  border-radius:12px;
  background:rgba(255,255,255,0.88);
  font-family:'Inter',sans-serif;
  /* 16px minimum: prevents Android IME batch-composition (backward typing) bug */
  font-size:16px; color:#181714; outline:none;
  direction: ltr;
  unicode-bidi: plaintext;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.field input::placeholder { color:#a8a196; }
.field input[type="password"]::-ms-reveal,
.field input[type="password"]::-ms-clear {
  display: none;
}
.field input:focus {
  border-color:var(--green);
  background:white;
  box-shadow:0 0 0 3px rgba(34,197,94,0.14);
}
.has-eye input { padding-right:38px; }

/* Terms and Conditions */
.terms-modal {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(17,24,39,0.42);
  backdrop-filter: blur(8px);
}
.terms-modal.active { display: flex; }
.terms-panel {
  width: min(560px, 100%);
  max-height: min(78vh, 680px);
  padding: 22px;
  border: 1px solid rgba(255,255,255,0.68);
  border-radius: 22px;
  background: rgba(255,255,255,0.96);
  box-shadow: 0 24px 70px rgba(0,0,0,0.24);
  animation: fadeUp 0.28s ease both;
}
.terms-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 12px;
}
.terms-title {
  font-size: 13px;
  font-weight: 800;
  color: #181714;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 3px;
}
.terms-subtitle {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--green-dark);
  margin-top: 3px;
}
.terms-close {
  width: 34px;
  height: 34px;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 50%;
  background: rgba(243,244,246,0.9);
  color: #3b3833;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.2s, transform 0.2s;
}
.terms-close:hover {
  background: white;
  transform: translateY(-1px);
}
.terms-scroll {
  max-height: calc(min(78vh, 680px) - 112px);
  overflow-y: auto;
  padding-right: 8px;
  color: #3b3833;
  font-size: 12px;
  line-height: 1.55;
}
.terms-scroll h3 {
  color: #181714;
  font-size: 12.5px;
  margin: 12px 0 4px;
}
.terms-scroll h3:first-child { margin-top: 0; }
.terms-scroll ul {
  margin: 5px 0 8px 18px;
}
.terms-scroll p { margin-bottom: 8px; }
.terms-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 0 0 15px;
  color: #3b3833;
  font-size: 12.5px;
  line-height: 1.45;
}
.terms-check input {
  width: 17px;
  height: 17px;
  margin-top: 1px;
  accent-color: var(--green-dark);
  flex-shrink: 0;
}
.terms-check label { cursor: pointer; }
.terms-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--green-dark);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* ── Account type ── */
@media (max-width: 600px) {
  .terms-modal {
    align-items: flex-end;
    padding: 12px;
  }
  .terms-panel {
    max-height: 86svh;
    padding: 18px 16px;
    border-radius: 18px;
  }
  .terms-scroll {
    max-height: calc(86svh - 96px);
    font-size: 12.5px;
  }
  .terms-check { margin-top: 2px; }
}

.acct-label {
  display:block; font-size:12.5px; font-weight:600;
  color:#3b3833; margin-bottom:8px;
}
.acct-row { display:flex; gap:10px; margin-bottom:20px; }
.acct-btn {
  flex:1; padding:13px 10px;
  border:1.5px solid rgba(0,0,0,0.1);
  border-radius:13px;
  background:rgba(255,255,255,0.88);
  text-align:left; cursor:pointer;
  transition:all 0.2s; font-family:'Inter',sans-serif;
}
.acct-btn .aname { font-size:14px; font-weight:600; color:#181714; display:block; margin-bottom:2px; }
.acct-btn .adesc { font-size:11.5px; color:#514d46; }
.acct-btn.selected { border-color:var(--green); background:rgba(220,252,231,0.72); }
.acct-btn.selected .aname { color:var(--green-dark); }

/* ── CTA ── */
.cta-btn {
  width:100%; padding:15px;
  background:var(--green); color:white; border:none;
  border-radius:14px; font-family:'Inter',sans-serif;
  font-size:15px; font-weight:700;
  cursor:pointer; letter-spacing:0.2px;
  display:flex; align-items:center; justify-content:center; gap:8px;
  box-shadow:0 4px 20px rgba(34,197,94,0.45);
  transition:all 0.2s; margin-bottom:17px;
}
.cta-btn:hover {
  background:var(--green-dark); transform:translateY(-1px);
  box-shadow:0 6px 24px rgba(34,197,94,0.52);
}

/* ── Bottom link ── */
.bottom-link { text-align:center; font-size:13.5px; color:#4b5563; }
.bottom-link a {
  color:var(--green-dark); font-weight:700; cursor:pointer; text-decoration:none;
}
.bottom-link a:hover { text-decoration:underline; }

/* ── Toast notifications ── */
#toast-container {
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
}
.toast {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 14px;
  font-family: 'Figtree', sans-serif;
  font-size: 13.5px;
  font-weight: 500;
  color: white;
  backdrop-filter: blur(16px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
  pointer-events: auto;
  min-width: 280px;
  max-width: 360px;
  animation: toastIn 0.4s cubic-bezier(0.22,1,0.36,1) both;
}
.toast.success { background: rgba(22,163,74,0.92); border: 1px solid rgba(255,255,255,0.2); }
.toast.error   { background: rgba(220,38,38,0.92);  border: 1px solid rgba(255,255,255,0.15); }
.toast.info    { background: rgba(37,99,235,0.92);  border: 1px solid rgba(255,255,255,0.15); }
.toast.fadeout { animation: toastOut 0.35s ease forwards; }
@keyframes toastIn  { from { opacity:0; transform:translateX(30px); } to { opacity:1; transform:translateX(0); } }
@keyframes toastOut { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(30px); } }

/* ── Loading spinner on button ── */
.cta-btn.loading { opacity: 0.75; pointer-events: none; cursor: not-allowed; }
.spinner {
  width: 16px; height: 16px;
  border: 2.5px solid rgba(255,255,255,0.35);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Session banner ── */
#session-banner {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(22,163,74,0.95);
  backdrop-filter: blur(12px);
  color: white; text-align: center;
  padding: 14px 20px;
  font-family: 'Figtree', sans-serif;
  font-size: 14px; font-weight: 500;
  display: none;
  gap: 12px; align-items: center; justify-content: center;
  flex-wrap: wrap;
}
#session-banner.show { display: flex; }
#session-banner a {
  color: #bbf7d0; font-weight: 700; cursor: pointer;
  text-decoration: underline;
}

/* Premium photographic garden-studio shell */
.scene-bg {
  transform: none;
  background-color: #102f23;
  /* Two layers: the hero photo sits on top of a 40px inline placeholder.
     The placeholder needs no network round-trip, so the panel never flashes
     the flat #102f23 base colour while the hero jpeg is still downloading. */
  background-image:
    url("{{ asset('images/ferosa-login-hero.jpg') }}"),
    url("data:image/png;base64,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");
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}
.scene-bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(8,29,21,.02) 0%, rgba(8,29,21,.01) 47%, rgba(8,29,21,.16) 72%, rgba(8,29,21,.32) 100%);
}
.scene-bg::after {
  content: none;
}
.scene-bg > svg { display: none !important; }
.scene-overlay {
  background:
    linear-gradient(180deg, rgba(8,29,21,.02), rgba(8,29,21,.10)),
    linear-gradient(90deg, transparent 0%, transparent 50%, rgba(8,29,21,.08) 70%, rgba(8,29,21,.26) 100%);
}
.scene {
  align-items: stretch;
  justify-content: flex-end;
  min-height: var(--app-vh);
  padding: clamp(18px, 2.2vw, 34px);
}
.page.active {
  position: relative;
  width: min(43vw, 520px);
  max-height: calc(var(--app-vh) - clamp(36px, 4.4vw, 68px));
  overflow-y: auto;
  /* Two declarations, deliberately. Plain `center` would centre the short
     login form nicely but leave the tall signup form overflowing in both
     directions, with the top half unreachable by scrolling. `safe center`
     centres only while the card fits and falls back to start alignment once
     it doesn't. Browsers without safe alignment drop the second declaration
     and keep the top-anchored first one. */
  align-items: flex-start;
  align-items: safe center;
  justify-content: center;
  border: 1px solid rgba(255,255,255,.82);
  border-radius: 28px;
  background: rgba(248,247,243,.93);
  box-shadow: 0 28px 80px rgba(3,18,12,.28), inset 0 1px 0 rgba(255,255,255,.9);
  backdrop-filter: blur(22px) saturate(120%);
  -webkit-backdrop-filter: blur(22px) saturate(120%);
  scrollbar-width: thin;
}
.page.active::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 4px;
  background: #fff;
  z-index: 2;
}
.form-card {
  max-width: 420px;
  /* Horizontal auto margins only: a vertical `auto` would win over the
     `align-items: safe center` above and reinstate unsafe centring. */
  margin: 0 auto;
  padding: 42px 32px 34px;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
}
.brand { display: none; }
.brand-icon { background: #f6f3ea; box-shadow: 0 10px 30px rgba(0,0,0,.15); }
.brand-icon svg path:first-child { fill: #1b5239; }
.brand-name {
  font-family: 'Fraunces', Georgia, serif;
  font-size: 28px;
  letter-spacing: -.02em;
}
.brand-slogan {
  margin-top: 28px;
  max-width: 430px;
  font-family: 'Fraunces', Georgia, serif;
  font-size: clamp(34px, 3.6vw, 56px);
  line-height: 1.03;
  letter-spacing: -.035em;
}
.brand-sub { margin-top: 18px; max-width: 420px; color: rgba(255,255,255,.68); font-size: 14px; line-height: 1.75; }
.auth-proof { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 26px; }
.auth-proof span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 11px;
  border: 1px solid rgba(255,255,255,.14);
  border-radius: 999px;
  background: rgba(255,255,255,.07);
  color: rgba(255,255,255,.78);
  font-size: 11px;
  font-weight: 600;
  backdrop-filter: blur(8px);
}
.auth-proof span::before { content: ''; width: 5px; height: 5px; border-radius: 999px; background: #82bd98; }
.form-title {
  font-family: 'Fraunces', Georgia, serif;
  color: var(--ink);
  letter-spacing: -.035em;
}
.form-subtitle { color: #706b61; line-height: 1.6; }
.auth-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  margin: 0 auto 22px;
  color: #1b5239;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .15em;
  text-transform: uppercase;
}
.auth-mark-icon {
  display: inline-flex;
  width: 31px;
  height: 31px;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: #123426;
  color: #eef7f1;
  box-shadow: 0 8px 18px rgba(18,52,38,.18);
}
.field-label { color: #3b4a42; }
.field input {
  min-height: 50px;
  border: 1px solid #dedbd2;
  border-radius: 13px;
  background: #fff;
  font-family: 'DM Sans', sans-serif;
  color: var(--ink);
}
.field input:focus { border-color: #347f57; box-shadow: 0 0 0 4px rgba(52,127,87,.12); }
.cta-btn {
  min-height: 52px;
  border-radius: 13px;
  background: #1b5239;
  box-shadow: 0 12px 28px rgba(27,82,57,.22);
  font-family: 'DM Sans', sans-serif;
}
.cta-btn:hover { background: #123426; transform: translateY(-1px); }
.bottom-link a, .bottom-link button, .forgot-link { color: #236746; }
.forgot-link, .bottom-link button {
  border: 0;
  padding: 0;
  background: transparent;
  font-family: inherit;
}
.bottom-link button { font-size: inherit; font-weight: 700; cursor: pointer; }
.bottom-link button:hover { text-decoration: underline; }
.input-eye {
  width: 38px;
  height: 38px;
  right: 6px;
  justify-content: center;
  border: 0;
  border-radius: 9px;
  background: transparent;
}
.input-eye:hover { color: #1b5239; background: #eef7f1; }
.secure-note {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  margin-top: 26px;
  padding-top: 18px;
  border-top: 1px solid #e2ded4;
  color: #827c70;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: .02em;
}
.help-badge { display: none; }

@media (max-width: 900px) {
  .scene { padding: 16px; }
  .page.active { width: min(52vw, 500px); max-height: calc(var(--app-vh) - 32px); }
}
@media (max-width: 700px) {
  .scene-bg { background-position: 69% center; }
  .scene-overlay { background: rgba(8,29,21,.22); }
  /* The card floats: it hugs its own content and centres in the scene rather
     than stretching to a full-height panel, so the garden sits around it on
     all four sides. `safe center` again - the signup form is taller than the
     screen, and it has to fall back to top alignment when it overflows. */
  .scene {
    display: flex;
    align-items: safe center;
    justify-content: center;
    padding: 22px 14px;
  }
  .page.active {
    width: 100%;
    max-height: none;
    min-height: 0;
    border-radius: 24px;
    background: rgba(248,247,243,.94);
  }
  .brand { display: none; }
  .form-card { max-width: 440px; padding: 42px 22px 30px; }
  .form-title { font-size: 32px; }
  .auth-mark { margin-bottom: 26px; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
}
</style>
@include('partials.a11y-focus')

  <script>
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        window.location.reload();
      }
    });

    // ── Keyboard-stable viewport height ──────────────────────────────────
    // The Android shell declares adjustResize, so opening the soft keyboard
    // really does shrink the WebView window. Left alone, every 100svh-based
    // min-height recomputes and the card visibly jumps upward mid-typing.
    // Pin --app-vh to the keyboard-less height: adopt a width change (a real
    // rotation or split-screen resize) and a height *increase* (keyboard
    // dismissed), but ignore a height-only shrink.
    (function () {
      var el = document.documentElement;
      var lockedWidth = window.innerWidth;
      var lockedHeight = window.innerHeight;

      function apply(height) {
        el.style.setProperty('--app-vh', height + 'px');
      }

      apply(lockedHeight);

      window.addEventListener('resize', function () {
        var width = window.innerWidth;
        var height = window.innerHeight;
        if (width !== lockedWidth || height > lockedHeight) {
          lockedWidth = width;
          lockedHeight = height;
          apply(height);
        }
      });
    })();
  </script>
</head>
<body>


<!-- ── Toast notification container ── -->
<div id="toast-container"></div>

<!-- ── Already-logged-in session banner ── -->
<div id="session-banner">
  <span id="session-msg">You're already signed in.</span>
  <a onclick="handleSignOut()">Sign out</a>
  <a href="ferosa-home.html">Go to Dashboard →</a>
</div>

<div class="scene-bg" id="sceneBg">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" style="width:100%;height:100%;display:block;">
  <defs>
    <radialGradient id="sky" cx="50%" cy="40%" r="70%">
      <stop offset="0%" stop-color="#c8e6c9"/>
      <stop offset="40%" stop-color="#81c784"/>
      <stop offset="100%" stop-color="#1b5239"/>
    </radialGradient>
    <radialGradient id="bgGlow" cx="55%" cy="45%" r="50%">
      <stop offset="0%" stop-color="#f8bbd0" stop-opacity="0.6"/>
      <stop offset="100%" stop-color="#f8bbd0" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="petalGrad1" cx="40%" cy="30%" r="70%">
      <stop offset="0%" stop-color="#fce4ec"/>
      <stop offset="50%" stop-color="#f48fb1"/>
      <stop offset="100%" stop-color="#c2185b"/>
    </radialGradient>
    <radialGradient id="petalGrad2" cx="40%" cy="30%" r="70%">
      <stop offset="0%" stop-color="#fce4ec"/>
      <stop offset="50%" stop-color="#f06292"/>
      <stop offset="100%" stop-color="#ad1457"/>
    </radialGradient>
    <radialGradient id="petalGrad3" cx="50%" cy="20%" r="80%">
      <stop offset="0%" stop-color="#fce4ec"/>
      <stop offset="60%" stop-color="#ec407a"/>
      <stop offset="100%" stop-color="#880e4f"/>
    </radialGradient>
    <radialGradient id="centerGrad" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#fff9c4"/>
      <stop offset="60%" stop-color="#f9a825"/>
      <stop offset="100%" stop-color="#e65100"/>
    </radialGradient>
    <filter id="blur1"><feGaussianBlur stdDeviation="18"/></filter>
    <filter id="blur2"><feGaussianBlur stdDeviation="8"/></filter>
    <filter id="softShadow"><feDropShadow dx="0" dy="4" stdDeviation="12" flood-color="#880e4f" flood-opacity="0.3"/></filter>
  </defs>

  <!-- Deep green background -->
  <rect width="1440" height="900" fill="url(#sky)"/>

  <!-- Background bokeh blobs -->
  <circle cx="120" cy="80" r="90" fill="#559e74" opacity="0.25" filter="url(#blur1)"/>
  <circle cx="1350" cy="120" r="110" fill="#236746" opacity="0.3" filter="url(#blur1)"/>
  <circle cx="200" cy="800" r="130" fill="#347f57" opacity="0.28" filter="url(#blur1)"/>
  <circle cx="1300" cy="780" r="100" fill="#1b5239" opacity="0.35" filter="url(#blur1)"/>
  <circle cx="700" cy="50" r="80" fill="#66bb6a" opacity="0.2" filter="url(#blur1)"/>
  <circle cx="900" cy="860" r="120" fill="#236746" opacity="0.3" filter="url(#blur1)"/>

  <!-- Far background leaves (large, blurred) -->
  <ellipse cx="100" cy="300" rx="180" ry="60" fill="#236746" opacity="0.5" transform="rotate(-35 100 300)" filter="url(#blur2)"/>
  <ellipse cx="1380" cy="250" rx="200" ry="65" fill="#1b5239" opacity="0.55" transform="rotate(40 1380 250)" filter="url(#blur2)"/>
  <ellipse cx="50" cy="650" rx="160" ry="55" fill="#347f57" opacity="0.45" transform="rotate(-50 50 650)" filter="url(#blur2)"/>
  <ellipse cx="1420" cy="700" rx="170" ry="60" fill="#236746" opacity="0.5" transform="rotate(45 1420 700)" filter="url(#blur2)"/>
  <ellipse cx="600" cy="880" rx="200" ry="70" fill="#1b5239" opacity="0.4" transform="rotate(-10 600 880)" filter="url(#blur1)"/>
  <ellipse cx="880" cy="30" rx="180" ry="58" fill="#347f57" opacity="0.35" transform="rotate(15 880 30)" filter="url(#blur2)"/>

  <!-- Mid background stems & leaves -->
  <line x1="720" y1="900" x2="720" y2="380" stroke="#236746" stroke-width="14" stroke-linecap="round" opacity="0.7"/>
  <line x1="720" y1="700" x2="550" y2="550" stroke="#347f57" stroke-width="10" stroke-linecap="round" opacity="0.6"/>
  <line x1="720" y1="620" x2="900" y2="500" stroke="#347f57" stroke-width="10" stroke-linecap="round" opacity="0.6"/>
  <line x1="720" y1="780" x2="400" y2="700" stroke="#236746" stroke-width="8" stroke-linecap="round" opacity="0.5"/>
  <line x1="720" y1="750" x2="1020" y2="660" stroke="#236746" stroke-width="8" stroke-linecap="round" opacity="0.5"/>

  <!-- Leaves mid -->
  <ellipse cx="490" cy="510" rx="110" ry="38" fill="#33691e" transform="rotate(-38 490 510)"/>
  <ellipse cx="940" cy="470" rx="105" ry="36" fill="#33691e" transform="rotate(32 940 470)"/>
  <ellipse cx="340" cy="675" rx="90" ry="30" fill="#347f57" transform="rotate(-52 340 675)"/>
  <ellipse cx="1060" cy="635" rx="95" ry="32" fill="#347f57" transform="rotate(48 1060 635)"/>
  <!-- Leaf veins -->
  <line x1="430" y1="488" x2="555" y2="535" stroke="#559e74" stroke-width="2" opacity="0.5"/>
  <line x1="885" y1="445" x2="998" y2="498" stroke="#559e74" stroke-width="2" opacity="0.5"/>

  <!-- Small buds on stems -->
  <ellipse cx="560" cy="430" rx="14" ry="26" fill="#f48fb1" transform="rotate(-25 560 430)"/>
  <ellipse cx="890" cy="400" rx="14" ry="26" fill="#ec407a" transform="rotate(20 890 400)"/>
  <ellipse cx="460" cy="360" rx="11" ry="20" fill="#f48fb1" transform="rotate(-15 460 360)"/>
  <ellipse cx="990" cy="350" rx="11" ry="20" fill="#f06292" transform="rotate(18 990 350)"/>
  <ellipse cx="650" cy="310" rx="10" ry="18" fill="#fce4ec" transform="rotate(-8 650 310)"/>
  <ellipse cx="800" cy="300" rx="10" ry="18" fill="#fce4ec" transform="rotate(10 800 300)"/>

  <!-- Pink glow behind main flower -->
  <circle cx="720" cy="440" r="260" fill="url(#bgGlow)" filter="url(#blur1)"/>

  <!-- MAIN FLOWER — centered large bloom -->
  <g transform="translate(720,440)" filter="url(#softShadow)">
    <!-- 5 petals radiating out -->
    <!-- Petal 1: top -->
    <ellipse rx="72" ry="155" fill="url(#petalGrad1)" transform="rotate(0) translate(0,-90)" opacity="0.97"/>
    <!-- Petal 2: upper right -->
    <ellipse rx="72" ry="155" fill="url(#petalGrad2)" transform="rotate(72) translate(0,-90)" opacity="0.95"/>
    <!-- Petal 3: lower right -->
    <ellipse rx="72" ry="155" fill="url(#petalGrad3)" transform="rotate(144) translate(0,-90)" opacity="0.95"/>
    <!-- Petal 4: lower left -->
    <ellipse rx="72" ry="155" fill="url(#petalGrad2)" transform="rotate(216) translate(0,-90)" opacity="0.95"/>
    <!-- Petal 5: upper left -->
    <ellipse rx="72" ry="155" fill="url(#petalGrad1)" transform="rotate(288) translate(0,-90)" opacity="0.97"/>

    <!-- Petal highlight streaks -->
    <line x1="0" y1="-180" x2="0" y2="-30" stroke="rgba(255,255,255,0.35)" stroke-width="3" stroke-linecap="round" transform="rotate(0)"/>
    <line x1="0" y1="-180" x2="0" y2="-30" stroke="rgba(255,255,255,0.25)" stroke-width="2" stroke-linecap="round" transform="rotate(72)"/>
    <line x1="0" y1="-180" x2="0" y2="-30" stroke="rgba(255,255,255,0.3)" stroke-width="2.5" stroke-linecap="round" transform="rotate(144)"/>
    <line x1="0" y1="-180" x2="0" y2="-30" stroke="rgba(255,255,255,0.25)" stroke-width="2" stroke-linecap="round" transform="rotate(216)"/>
    <line x1="0" y1="-180" x2="0" y2="-30" stroke="rgba(255,255,255,0.3)" stroke-width="2.5" stroke-linecap="round" transform="rotate(288)"/>

    <!-- Flower center -->
    <circle r="38" fill="#fff176"/>
    <circle r="28" fill="url(#centerGrad)"/>
    <circle r="18" fill="#ffcc02"/>
    <!-- Stamens -->
    <circle cx="0" cy="-22" r="4.5" fill="#fff"/>
    <circle cx="20" cy="-10" r="4.5" fill="#fff"/>
    <circle cx="13" cy="17" r="4.5" fill="#fff"/>
    <circle cx="-13" cy="17" r="4.5" fill="#fff"/>
    <circle cx="-20" cy="-10" r="4.5" fill="#fff"/>
    <!-- Stamen dots -->
    <circle cx="0" cy="-22" r="2.5" fill="#f57f17"/>
    <circle cx="20" cy="-10" r="2.5" fill="#f57f17"/>
    <circle cx="13" cy="17" r="2.5" fill="#f57f17"/>
    <circle cx="-13" cy="17" r="2.5" fill="#f57f17"/>
    <circle cx="-20" cy="-10" r="2.5" fill="#f57f17"/>
  </g>

  <!-- Foreground leaf overlaps (adds depth) -->
  <ellipse cx="150" cy="500" rx="200" ry="70" fill="#1b5239" opacity="0.7" transform="rotate(-30 150 500)"/>
  <ellipse cx="1310" cy="480" rx="190" ry="68" fill="#1b5239" opacity="0.65" transform="rotate(28 1310 480)"/>
  <ellipse cx="0" cy="750" rx="220" ry="75" fill="#236746" opacity="0.6" transform="rotate(-45 0 750)"/>
  <ellipse cx="1450" cy="750" rx="210" ry="72" fill="#236746" opacity="0.6" transform="rotate(42 1450 750)"/>

  <!-- Bokeh light spots (foreground) -->
  <circle cx="250" cy="180" r="22" fill="rgba(255,255,255,0.12)" filter="url(#blur2)"/>
  <circle cx="1180" cy="210" r="28" fill="rgba(255,255,255,0.1)" filter="url(#blur2)"/>
  <circle cx="400" cy="820" r="18" fill="rgba(255,255,255,0.1)" filter="url(#blur2)"/>
  <circle cx="1100" cy="800" r="24" fill="rgba(255,255,255,0.1)" filter="url(#blur2)"/>
  <circle cx="80" cy="400" r="16" fill="rgba(255,200,150,0.15)" filter="url(#blur2)"/>
  <circle cx="1380" cy="380" r="20" fill="rgba(255,200,150,0.12)" filter="url(#blur2)"/>
</svg>
</div>
<div class="scene-overlay"></div>

<div class="brand">
  <div class="brand-logo">
    <div class="brand-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <path d="M12 3C12 3 7 6.5 7 12c0 2.76 1.34 5.22 3.4 6.74.38-.48.93-.74 1.6-.74s1.22.26 1.6.74C15.66 17.22 17 14.76 17 12c0-5.5-5-9-5-9z" fill="white"/>
        <path d="M12 3c0 0 0 4.5 3 7.5" stroke="rgba(255,255,255,0.45)" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
    </div>
    <span class="brand-name">Ferosa Landscaping</span>
  </div>
  <p class="brand-slogan">Plan. Book.<br>Grow beautifully.</p>
  <p class="brand-sub">Estimate projects, schedule landscaping services, shop garden essentials, and follow every update in one place.</p>
  <div class="auth-proof" aria-label="Ferosa system features">
    <span>Project estimates</span>
    <span>Service scheduling</span>
    <span>Order tracking</span>
  </div>
</div>

<div class="scene">

  <!-- LOGIN -->
  <div class="page {{ in_array($active ?? 'login', ['signup', 'forgot']) ? '' : 'active' }}" id="page-login">
    <div class="form-card">
      <div class="auth-mark" aria-label="Ferosa Landscaping">
        <span class="auth-mark-icon" aria-hidden="true">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5c-6.6.2-11.3 3-13.5 8.3M5.2 19c1.1-5.2 4.8-8.7 10.8-10.7M19.5 4.5c.4 6.8-2.8 11.3-8.1 11.8-2.3.2-4.3-.8-5.4-3.5"/></svg>
        </span>
        <span>Ferosa Landscaping</span>
      </div>
      <div class="form-header">
        <h1 class="form-title">Welcome Back</h1>
        <p class="form-subtitle">Sign in to plan, book, and follow your landscaping projects.</p>
      </div>

      <form id="login-form" method="POST" action="{{ route('login.submit') }}">
      @csrf
      <div class="field">
        <label class="field-label" for="login-email">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span>
          <input id="login-email" name="email" type="email" inputmode="email" placeholder="you@example.com" dir="ltr" autocomplete="email" autocorrect="off" autocapitalize="off" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <div class="field-label-row">
          <label class="field-label" for="login-password" style="margin:0">Password</label>
          <button type="button" class="forgot-link" onclick="handleForgotPassword()">Forgot password?</button>
        </div>
        <div class="input-wrap has-eye">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input id="login-password" name="password" type="password" placeholder="••••••••" dir="ltr" autocomplete="current-password" required>
          <button type="button" class="input-eye" onclick="togglePw(this)" aria-label="Show or hide password"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
      </div>

      <button type="submit" id="login-btn" class="cta-btn" style="margin-top:8px">
        Sign In
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
      </form>

      <p class="bottom-link">Don't have an account? <button type="button" onclick="switchTo('signup')">Sign up</button></p>
      <p class="secure-note">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        Secure access &middot; Orani, Bataan
      </p>
    </div>
  </div>

  <!-- SIGNUP -->
  <div class="page {{ ($active ?? 'login') === 'signup' ? 'active' : '' }}" id="page-signup">
    <div class="form-card">
      <div class="form-header">
        <h1 class="form-title">Create Account</h1>
        <p class="form-subtitle">Join Ferosa Landscaping and start designing your dream garden</p>
      </div>

      <form id="signup-form" method="POST" action="{{ route('register.submit') }}">
      @csrf
      <div class="field">
        <label class="field-label" for="signup-last-name">Last Name / Surname</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
          <input id="signup-last-name" name="last_name" type="text" placeholder="Dela Cruz" dir="ltr" autocomplete="family-name" autocorrect="off" autocapitalize="words" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="signup-first-name">First Name</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
          <input id="signup-first-name" name="first_name" type="text" placeholder="Juan" dir="ltr" autocomplete="given-name" autocorrect="off" autocapitalize="words" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="signup-middle-name">Middle Name <span style="font-weight:400;color:#a8a196">(Optional)</span></label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
          <input id="signup-middle-name" name="middle_name" type="text" placeholder="Santos" dir="ltr" autocomplete="additional-name" autocorrect="off" autocapitalize="words" spellcheck="false">
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="signup-email">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span>
          <input id="signup-email" name="email" type="email" inputmode="email" placeholder="you@example.com" dir="ltr" autocomplete="email" autocorrect="off" autocapitalize="off" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="signup-phone">Mobile Number</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
          <input id="signup-phone" name="phone_number" type="tel" inputmode="tel" placeholder="+63 912 345 6789" dir="ltr" autocomplete="tel" autocorrect="off" autocapitalize="off" spellcheck="false" required>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="signup-password">Password</label>
        <div class="input-wrap has-eye">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input id="signup-password" name="password" type="password" minlength="12" placeholder="••••••••" dir="ltr" autocomplete="new-password" required>
          <button type="button" class="input-eye" onclick="togglePw(this)" aria-label="Show or hide password"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="signup-password-confirm">Confirm Password</label>
        <div class="input-wrap has-eye">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input id="signup-password-confirm" name="password_confirmation" type="password" minlength="12" placeholder="••••••••" dir="ltr" autocomplete="new-password" required>
          <button type="button" class="input-eye" onclick="togglePw(this)" aria-label="Show or hide password confirmation"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
      </div>

      <div class="terms-modal" id="terms-modal" role="dialog" aria-modal="true" aria-labelledby="terms-heading" onclick="closeTermsModal(event)">
        <div class="terms-panel" onclick="event.stopPropagation()">
          <div class="terms-head">
            <div>
              <div class="terms-title" id="terms-heading">FEROSA LANDSCAPING</div>
              <div class="terms-subtitle">Terms and Conditions</div>
            </div>
            <button type="button" class="terms-close" onclick="closeTermsModal()" aria-label="Close Terms and Conditions">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
        <div class="terms-scroll" tabindex="0">
          <h3>1. Acceptance of Agreement</h3>
          <p>By accepting a quotation, signing a service agreement, making a payment, or authorizing the commencement of work, the client agrees to these Terms and Conditions.</p>

          <h3>2. Scope of Services</h3>
          <p>Ferosa Landscaping provides professional landscaping and plant-related services, including but not limited to:</p>
          <ul>
            <li>Landscape design and installation</li>
            <li>Supply and installation of ornamental plants, shrubs, trees, and other greenery</li>
            <li>Garden development and beautification</li>
            <li>Lawn installation and maintenance</li>
            <li>Garden and landscape maintenance</li>
            <li>Tree and shrub planting</li>
            <li>Irrigation system installation and maintenance</li>
            <li>Soil preparation, mulching, and fertilization</li>
            <li>Other landscaping services as agreed upon in writing</li>
          </ul>
          <p>Any services requested outside the approved quotation or agreement may be subject to additional charges.</p>

          <h3>3. Quotations</h3>
          <p>All quotations are valid for thirty (30) days from the date of issue unless otherwise stated. Prices are based on current labor, plant, and material costs. Ferosa Landscaping reserves the right to adjust quotations if project requirements or material costs change before work begins.</p>

          <h3>4. Payment Terms</h3>
          <p>A 50% down payment may be required before the project starts. The remaining balance is due upon project completion unless otherwise agreed in writing. Late payments may incur additional charges as permitted by applicable law.</p>

          <h3>5. Project Schedule</h3>
          <p>Project timelines are estimates and may be affected by weather conditions, availability of plants and materials, site accessibility, unexpected site conditions, and circumstances beyond the company's reasonable control. Clients will be informed of any significant schedule changes.</p>

          <h3>6. Client Responsibilities</h3>
          <p>The client agrees to provide safe and unobstructed access to the project site, ensure accurate property boundaries, obtain necessary permits if required unless otherwise agreed, and inform the company of underground utilities, irrigation lines, septic systems, or hidden structures before work begins.</p>
          <p>Ferosa Landscaping shall not be liable for damages resulting from undisclosed site conditions.</p>

          <h3>7. Plant Care and Warranty</h3>
          <p>All plants supplied by Ferosa Landscaping are healthy and inspected before installation. Plant survival depends on proper watering, sunlight, soil conditions, maintenance, pests, and weather. Plants damaged due to neglect, improper care, pests, diseases, natural disasters, or extreme weather conditions are not covered under warranty unless otherwise specified in writing.</p>

          <h3>8. Changes to the Project</h3>
          <p>Any request to modify the approved project scope must be agreed upon in writing. Additional work may result in changes to the project cost and completion date.</p>

          <h3>9. Cancellation</h3>
          <p>Clients may cancel the project before work begins. Deposits may be non-refundable if plants, materials, or labor have already been scheduled or purchased. If work has already started, the client shall pay for completed work, materials used, and any applicable cancellation costs.</p>

          <h3>10. Limitation of Liability</h3>
          <p>Ferosa Landscaping shall not be responsible for delays or damages caused by events beyond its reasonable control, including severe weather, natural disasters, supplier delays, or government restrictions. The company's total liability shall not exceed the amount paid by the client for the services provided.</p>

          <h3>11. Property Damage</h3>
          <p>Reasonable care will be taken while performing services. However, the company is not responsible for damage resulting from hidden underground utilities, pre-existing structural defects, weak surfaces, or conditions unknown before work commenced.</p>

          <h3>12. Use of Project Photos</h3>
          <p>Ferosa Landscaping reserves the right to take photographs of completed projects for portfolio, promotional, and marketing purposes unless the client provides written notice declining such use.</p>

          <h3>13. Dispute Resolution</h3>
          <p>Both parties agree to resolve disputes through good-faith negotiation before pursuing any legal action. Any legal disputes shall be governed by the laws of the Republic of the Philippines.</p>

          <h3>14. Governing Law</h3>
          <p>These Terms and Conditions shall be governed and interpreted in accordance with the laws of the Republic of the Philippines.</p>

          <h3>15. Amendments</h3>
          <p>Ferosa Landscaping reserves the right to amend these Terms and Conditions at any time. Updated versions shall apply to future projects and service agreements.</p>

          <h3>16. Contact Information</h3>
          <p>For questions regarding these Terms and Conditions, clients may contact Ferosa Landscaping through its official contact number, email address, or social media pages.</p>
        </div>
        </div>
      </div>

      <div class="terms-check">
        <input id="signup-terms" name="terms_accepted" type="checkbox" value="1" required>
        <label for="signup-terms">I have read and agree to the Ferosa Landscaping <span class="terms-link" role="button" tabindex="0" onclick="openTermsModal(event)" onkeydown="openTermsModalFromKey(event)">Terms and Conditions</span>.</label>
      </div>

      <button id="signup-btn" type="submit" class="cta-btn">
        Create Account
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
      </form>

      <p class="bottom-link">Already have an account? <a onclick="switchTo('login')">Sign in</a></p>
    </div>
  </div>

  <!-- FORGOT PASSWORD: Enter Phone -->
  <div class="page {{ ($active ?? 'login') === 'forgot' ? 'active' : '' }}" id="page-forgot">
    <div class="form-card">
      <div class="form-header">
        <h1 class="form-title">Forgot Password</h1>
        <p class="form-subtitle">Enter your registered mobile number to receive a verification code.</p>
      </div>
      <div class="field">
        <label class="field-label">Mobile Number</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
          <input id="forgot-phone" type="text" inputmode="tel" placeholder="+63 912 345 6789" dir="ltr" autocomplete="tel" autocorrect="off" autocapitalize="off" spellcheck="false">
        </div>
      </div>
      <button id="forgot-btn" class="cta-btn" onclick="sendOtp()">
        Send Verification Code
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
      <p class="bottom-link"><a onclick="switchTo('login')">Back to Sign In</a></p>
    </div>
  </div>

  <!-- FORGOT PASSWORD: Enter OTP -->
  <div class="page" id="page-otp">
    <div class="form-card">
      <div class="form-header">
        <h1 class="form-title">Enter Verification Code</h1>
        <p class="form-subtitle">We sent a 6-digit code to your mobile number. It expires in 10 minutes.</p>
      </div>
      <div class="field">
        <label class="field-label">6-Digit Code</label>
        <div class="input-wrap">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input id="otp-code" type="text" inputmode="numeric" maxlength="6" placeholder="123456" dir="ltr" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false">
        </div>
      </div>
      <button id="otp-btn" class="cta-btn" onclick="verifyOtp()">
        Verify Code
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
      <p class="bottom-link"><a onclick="switchTo('forgot')">Resend code</a> &nbsp;·&nbsp; <a onclick="switchTo('login')">Cancel</a></p>
    </div>
  </div>

  <!-- FORGOT PASSWORD: New Password -->
  <div class="page" id="page-newpassword">
    <div class="form-card">
      <div class="form-header">
        <h1 class="form-title">Set New Password</h1>
        <p class="form-subtitle">Choose a strong password for your account.</p>
      </div>
      <div class="field">
        <label class="field-label">New Password</label>
        <div class="input-wrap has-eye">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input id="new-password" type="password" placeholder="••••••••" dir="ltr" autocomplete="new-password">
          <span class="input-eye" onclick="togglePw(this)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></span>
        </div>
      </div>
      <div class="field">
        <label class="field-label">Confirm New Password</label>
        <div class="input-wrap has-eye">
          <span class="input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input id="new-password-confirm" type="password" placeholder="••••••••" dir="ltr" autocomplete="new-password">
          <span class="input-eye" onclick="togglePw(this)"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></span>
        </div>
      </div>
      <button id="newpw-btn" class="cta-btn" onclick="resetPassword()">
        Reset Password
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
      <p class="bottom-link"><a onclick="switchTo('login')">Cancel</a></p>
    </div>
  </div>

</div><!-- /scene -->

<script>
function showToast(message, type = 'info', duration = 4500) {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  const icons = {
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    error:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    info:    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };
  toast.innerHTML = icons[type] || icons.info;
  const messageText = document.createElement('span');
  messageText.textContent = String(message);
  toast.appendChild(messageText);
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('fadeout');
    toast.addEventListener('animationend', () => toast.remove());
  }, duration);
}

function setLoading(btnId, loading, originalHTML) {
  const btn = document.getElementById(btnId);
  if (!btn) return;
  if (loading) {
    btn._originalHTML = btn.innerHTML;
    btn.classList.add('loading');
    btn.innerHTML = '<div class="spinner"></div> Processing...';
  } else {
    btn.classList.remove('loading');
    btn.innerHTML = originalHTML || btn._originalHTML || btn.innerHTML;
  }
}

function switchTo(target) {
  const fromPage = document.querySelector('.page.active');
  const toPage = document.getElementById('page-' + target);
  if (!fromPage || fromPage === toPage) return;
  const card = fromPage.querySelector('.form-card');
  if (card) card.style.animation = 'fadeDown 0.28s ease forwards';
  setTimeout(() => {
    fromPage.classList.remove('active');
    toPage.classList.add('active');
    const newCard = toPage.querySelector('.form-card');
    if (newCard) {
      newCard.style.animation = 'none';
      requestAnimationFrame(() => {
        newCard.style.animation = 'fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both';
      });
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, 260);
}

function togglePw(eye) {
  const input = eye.previousElementSibling;
  input.type = input.type === 'password' ? 'text' : 'password';
}

function openTermsModal(event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  const modal = document.getElementById('terms-modal');
  if (!modal) return;
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  const scrollBox = modal.querySelector('.terms-scroll');
  if (scrollBox) scrollBox.focus();
}

function openTermsModalFromKey(event) {
  if (event.key === 'Enter' || event.key === ' ') openTermsModal(event);
}

function closeTermsModal(event) {
  if (event && event.currentTarget !== event.target) return;
  const modal = document.getElementById('terms-modal');
  if (!modal) return;
  modal.classList.remove('active');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeTermsModal();
});

function selectAcct(btn) {
  btn.closest('.acct-row').querySelectorAll('.acct-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
}

// ── Android WebView: fix backward/RTL typing ──────────────────────────────
// On focus, move cursor to end of any existing value (prevents IME inserting at pos 0)
document.addEventListener('focusin', event => {
  const el = event.target;
  if (!el || el.tagName !== 'INPUT') return;
  // Force LTR direction at the DOM level
  el.setAttribute('dir', 'ltr');
  // Move cursor to end
  const len = el.value.length;
  try {
    el.setSelectionRange(len, len);
  } catch (e) { /* password/checkbox inputs may throw */ }
});

// ── Android WebView: hold the page still while the keyboard is up ─────────
// Chromium on Android runs its own "scroll the focused editable into view"
// pass, and it re-runs on every caret update - i.e. on every keystroke. It
// aims to centre the caret in the space left above the keyboard, so on a tall
// form like signup the page creeps upward as you type and the heading walks
// off the top of the screen. No CSS can prevent that; the only fix is to
// decide the scroll position ourselves once and put it back whenever
// something else moves it.
//
// The field is still guaranteed to sit above the keyboard, and a real finger
// drag still scrolls freely - only unrequested programmatic scrolling is
// undone.
(function () {
  // Touch input only. On a desktop pointer there is no keyboard inset to
  // fight, and pinning would swallow legitimate wheel and scrollbar scrolling.
  if (!window.matchMedia || !window.matchMedia('(pointer: coarse)').matches) return;

  const CLEARANCE = 16;
  let pinnedY = null;
  let userDragging = false;
  let releaseTimer = null;
  let settleTimer = null;

  function viewportHeight() {
    return (window.visualViewport && window.visualViewport.height) || window.innerHeight;
  }

  function isTextField(el) {
    return el && el.tagName === 'INPUT' && el.type !== 'checkbox' && el.type !== 'button';
  }

  // Smallest scroll offset that clears the field of the keyboard. Returns the
  // current offset unchanged when the field is already comfortably in view.
  function restingOffsetFor(el) {
    const box = el.getBoundingClientRect();
    const viewH = viewportHeight();
    if (box.bottom > viewH - CLEARANCE) {
      return window.scrollY + (box.bottom - viewH + CLEARANCE);
    }
    if (box.top < CLEARANCE) {
      return Math.max(0, window.scrollY + box.top - CLEARANCE);
    }
    return window.scrollY;
  }

  document.addEventListener('focusin', event => {
    const el = event.target;
    if (!isTextField(el)) return;
    // Hold the pre-keyboard position until the keyboard has finished
    // animating, then settle on a final offset and defend it.
    pinnedY = window.scrollY;
    clearTimeout(settleTimer);
    settleTimer = setTimeout(() => {
      if (document.activeElement !== el) return;
      pinnedY = restingOffsetFor(el);
      window.scrollTo(0, pinnedY);
    }, 300);
  });

  document.addEventListener('focusout', event => {
    if (!isTextField(event.target)) return;
    clearTimeout(settleTimer);
    pinnedY = null;
  });

  window.addEventListener('scroll', () => {
    if (pinnedY === null || userDragging) return;
    if (Math.abs(window.scrollY - pinnedY) > 1) window.scrollTo(0, pinnedY);
  }, { passive: true });

  // A tap is not a scroll: only an actual drag hands control back to the user,
  // otherwise tapping a field would count as consent to the jump it triggers.
  window.addEventListener('touchmove', () => {
    userDragging = true;
    clearTimeout(releaseTimer);
  }, { passive: true });

  window.addEventListener('touchend', () => {
    if (!userDragging) return;
    clearTimeout(releaseTimer);
    releaseTimer = setTimeout(() => {
      userDragging = false;
      if (pinnedY !== null) pinnedY = window.scrollY;
    }, 260);
  }, { passive: true });
})();

document.addEventListener('mousemove', e => {
  const bg = document.getElementById('sceneBg');
  if (!bg) return;
  const x = (e.clientX / window.innerWidth - 0.5) * 12;
  const y = (e.clientY / window.innerHeight - 0.5) * 12;
  bg.style.transform = `scale(1.06) translate(${x}px,${y}px)`;
});

async function handleLogin() {
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;
  if (!email) return showToast('Please enter your email address.', 'error');
  if (!password) return showToast('Please enter your password.', 'error');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showToast('Please enter a valid email address.', 'error');

  setLoading('login-btn', true);
  try {
    const form = new URLSearchParams();
    form.append('email', email);
    form.append('password', password);
    const res = await fetch('{{ route('login.submit') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: form.toString(),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      if (data.errors) {
        const messages = Object.values(data.errors).flat();
        messages.forEach(msg => showToast(msg, 'error'));
        return;
      }
      throw new Error(data.message || 'Sign in failed');
    }
    window.location.href = data.redirectUrl || '{{ route('home') }}';
  } catch (err) {
    showToast(err.message || 'Sign in failed. Please try again.', 'error');
  } finally {
    setLoading('login-btn', false);
  }
}

async function handleSignup() {
  const lastName   = document.getElementById('signup-last-name').value.trim();
  const firstName  = document.getElementById('signup-first-name').value.trim();
  const middleName = document.getElementById('signup-middle-name').value.trim();
  const email = document.getElementById('signup-email').value.trim();
  const phone = document.getElementById('signup-phone').value.trim();
  const password = document.getElementById('signup-password').value;
  const passwordConfirm = document.getElementById('signup-password-confirm').value;
  const termsAccepted = document.getElementById('signup-terms').checked;

  if (!lastName) return showToast('Please enter your last name.', 'error');
  if (!firstName) return showToast('Please enter your first name.', 'error');
  if (!email) return showToast('Please enter your email address.', 'error');
  if (!phone) return showToast('Please enter your mobile number.', 'error');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showToast('Please enter a valid email address.', 'error');
  if (!password) return showToast('Please create a password.', 'error');
  if (password.length < 12) return showToast('Use at least 12 characters with uppercase, lowercase, a number, and a symbol.', 'error');
  if (password !== passwordConfirm) return showToast('Passwords do not match.', 'error');
  if (!termsAccepted) return showToast('Please read and accept the Terms and Conditions before creating an account.', 'error');

  setLoading('signup-btn', true);
  try {
    const form = new URLSearchParams();
    form.append('last_name', lastName);
    form.append('first_name', firstName);
    form.append('middle_name', middleName);
    form.append('email', email);
    form.append('phone_number', phone);
    form.append('password', password);
    form.append('password_confirmation', passwordConfirm);
    form.append('terms_accepted', termsAccepted ? '1' : '');
    const res = await fetch('{{ route('register.submit') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: form.toString(),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      if (data.errors) {
        const messages = Object.values(data.errors).flat();
        messages.forEach(msg => showToast(msg, 'error'));
        return;
      }
      throw new Error(data.message || 'Account creation failed');
    }
    window.location.href = data.redirectUrl || '{{ route('home') }}';
  } catch (err) {
    showToast(err.message || 'Account creation failed. Please try again.', 'error');
  } finally {
    setLoading('signup-btn', false);
  }
}

function handleForgotPassword() {
  switchTo('forgot');
}

// ── OTP state ────────────────────────────────────────────
let _otpPhone = '';
let _otpCode  = '';

async function sendOtp() {
  const phone = document.getElementById('forgot-phone').value.trim();
  if (!phone) return showToast('Please enter your mobile number.', 'error');

  setLoading('forgot-btn', true);
  try {
    const form = new URLSearchParams();
    form.append('phone_number', phone);
    const res = await fetch('{{ route('forgot.send-otp') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: form.toString(),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'Failed to send OTP.'];
      messages.forEach(m => showToast(m, 'error'));
      return;
    }
    _otpPhone = phone;
    showToast(data.message || 'OTP sent!', 'success');
    switchTo('otp');
  } catch (err) {
    showToast('Could not send OTP. Please try again.', 'error');
  } finally {
    setLoading('forgot-btn', false);
  }
}

async function verifyOtp() {
  const code = document.getElementById('otp-code').value.trim();
  if (!code || code.length !== 6) return showToast('Please enter the 6-digit code.', 'error');

  setLoading('otp-btn', true);
  try {
    const form = new URLSearchParams();
    form.append('phone_number', _otpPhone);
    form.append('otp', code);
    const res = await fetch('{{ route('forgot.verify-otp') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: form.toString(),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'Invalid OTP.'];
      messages.forEach(m => showToast(m, 'error'));
      return;
    }
    _otpCode = code;
    switchTo('newpassword');
  } catch (err) {
    showToast('Verification failed. Please try again.', 'error');
  } finally {
    setLoading('otp-btn', false);
  }
}

async function resetPassword() {
  const password = document.getElementById('new-password').value;
  const passwordConfirm = document.getElementById('new-password-confirm').value;
  if (!password) return showToast('Please enter a new password.', 'error');
  if (password.length < 12) return showToast('Use at least 12 characters with uppercase, lowercase, a number, and a symbol.', 'error');
  if (password !== passwordConfirm) return showToast('Passwords do not match.', 'error');

  setLoading('newpw-btn', true);
  try {
    const form = new URLSearchParams();
    form.append('phone_number', _otpPhone);
    form.append('otp', _otpCode);
    form.append('password', password);
    form.append('password_confirmation', passwordConfirm);
    const res = await fetch('{{ route('forgot.reset') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: form.toString(),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'Reset failed.'];
      messages.forEach(m => showToast(m, 'error'));
      return;
    }
    showToast('Password reset successfully! Please sign in.', 'success', 4000);
    _otpPhone = ''; _otpCode = '';
    setTimeout(() => switchTo('login'), 1500);
  } catch (err) {
    showToast('Could not reset password. Please try again.', 'error');
  } finally {
    setLoading('newpw-btn', false);
  }
}

function handleSignOut() {
  showToast('Please sign out from the dashboard.', 'info');
}

document.getElementById('login-form').addEventListener('submit', event => {
  event.preventDefault();
  handleLogin();
});

document.getElementById('signup-form').addEventListener('submit', event => {
  event.preventDefault();
  handleSignup();
});
</script>
</body>
</html>
