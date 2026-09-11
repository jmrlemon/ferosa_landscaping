{{-- Styled replacement for native confirm() on destructive actions.

     Any form can opt in:

       <form method="POST" data-confirm="Archive this order?">

     Optional: data-confirm-title, data-confirm-action (button label),
     data-confirm-tone="danger|default".

     Native confirm() renders as "localhost says…", cannot be styled, and is
     dismissed by muscle memory - poor for the actions it was guarding here
     (cancel order, archive, permanent delete). Failure mode matches the old
     one: if this script never runs, the form submits unguarded, exactly as
     onsubmit="return confirm(…)" did with JS disabled. --}}
@once
  <div id="ferosa-confirm"
       class="hidden fixed inset-0 z-[100] items-center justify-center p-4"
       role="alertdialog"
       aria-modal="true"
       aria-labelledby="ferosa-confirm-title"
       aria-describedby="ferosa-confirm-body">

    <div class="absolute inset-0 bg-surface-950/50 backdrop-blur-[2px]" data-confirm-dismiss></div>

    <div class="relative w-full max-w-sm rounded-2xl border border-surface-200 bg-white p-5 shadow-2xl">
      <div class="flex items-start gap-3">
        <span id="ferosa-confirm-icon"
              class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m0 3.75h.007M10.34 3.94l-7.6 13.16A1.5 1.5 0 004.04 19.5h15.92a1.5 1.5 0 001.3-2.4L13.66 3.94a1.5 1.5 0 00-2.6 0z"/>
          </svg>
        </span>
        <div class="min-w-0 flex-1">
          <h2 id="ferosa-confirm-title" class="text-sm font-bold text-surface-900">Are you sure?</h2>
          <p id="ferosa-confirm-body" class="mt-1 text-xs leading-relaxed text-surface-500"></p>
        </div>
      </div>

      <div class="mt-5 flex justify-end gap-2">
        <button type="button" id="ferosa-confirm-cancel"
                class="rounded-lg border border-surface-200 px-3.5 py-2 text-xs font-semibold text-surface-600 transition-colors hover:bg-surface-50">
          Cancel
        </button>
        <button type="button" id="ferosa-confirm-accept"
                class="rounded-lg bg-red-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-red-700">
          Confirm
        </button>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const root = document.getElementById('ferosa-confirm');
      if (!root) return;

      const titleEl  = document.getElementById('ferosa-confirm-title');
      const bodyEl   = document.getElementById('ferosa-confirm-body');
      const iconEl   = document.getElementById('ferosa-confirm-icon');
      const cancelEl = document.getElementById('ferosa-confirm-cancel');
      const acceptEl = document.getElementById('ferosa-confirm-accept');

      let pendingForm = null;
      let lastFocused = null;

      function open(form) {
        pendingForm = form;
        lastFocused = document.activeElement;

        const tone = form.dataset.confirmTone || 'danger';
        const danger = tone !== 'default';

        titleEl.textContent = form.dataset.confirmTitle || 'Are you sure?';
        bodyEl.textContent = form.dataset.confirm || '';
        acceptEl.textContent = form.dataset.confirmAction || 'Confirm';

        iconEl.className = 'mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full '
          + (danger ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600');
        acceptEl.className = 'rounded-lg px-3.5 py-2 text-xs font-semibold text-white transition-colors '
          + (danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-600 hover:bg-brand-700');

        root.classList.remove('hidden');
        root.classList.add('flex');
        document.body.style.overflow = 'hidden';

        // Destructive by default, so start on Cancel rather than Confirm.
        cancelEl.focus();
      }

      function close() {
        root.classList.add('hidden');
        root.classList.remove('flex');
        document.body.style.overflow = '';
        pendingForm = null;
        lastFocused?.focus?.();
      }

      document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

        // Second pass, after the user accepted.
        if (form.dataset.confirmAccepted === 'true') {
          delete form.dataset.confirmAccepted;
          return;
        }

        event.preventDefault();
        open(form);
      }, true);

      acceptEl.addEventListener('click', function () {
        const form = pendingForm;
        close();
        if (!form) return;

        form.dataset.confirmAccepted = 'true';

        // requestSubmit keeps native validation and fires submit again, where
        // the flag above lets it straight through. form.submit() would skip
        // validation entirely.
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      });

      cancelEl.addEventListener('click', close);

      root.addEventListener('click', function (event) {
        if (event.target.hasAttribute('data-confirm-dismiss')) close();
      });

      document.addEventListener('keydown', function (event) {
        if (root.classList.contains('hidden')) return;

        if (event.key === 'Escape') {
          event.preventDefault();
          close();
          return;
        }

        // Keep focus inside the dialog.
        if (event.key === 'Tab') {
          const stops = [cancelEl, acceptEl];
          const next = event.shiftKey ? -1 : 1;
          const at = stops.indexOf(document.activeElement);
          event.preventDefault();
          stops[(at + next + stops.length) % stops.length].focus();
        }
      });
    })();
  </script>
@endonce
