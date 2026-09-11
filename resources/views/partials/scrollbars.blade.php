{{-- Shared scrollbar chrome. Included by admin.partials.premium-theme (every
     admin screen) and layouts.customer (every customer screen).

     These rules used to be copy-pasted into three heads with three different
     answers: the customer side used #e5e5e5 (neutral), the admin side #d4d4d8
     (cool zinc), and only the admin side set `height`, so horizontal
     scrollbars were 5px there and browser-default on customer pages. The
     colours are now the warm surface ramp the rest of the UI uses. --}}
<style id="ferosa-scrollbars">
  ::-webkit-scrollbar { width: 5px; height: 5px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--color-surface-200); border-radius: 10px; }
  ::-webkit-scrollbar-thumb:hover { background: var(--color-surface-300); }
</style>
