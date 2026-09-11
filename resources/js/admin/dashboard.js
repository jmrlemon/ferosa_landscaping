/**
 * Admin dashboard behaviour.
 *
 * Extracted from admin/dashboard.blade.php so a syntax error fails the Vite
 * build instead of silently disabling every handler on the page at runtime.
 *
 * Server-rendered values arrive as JSON in #admin-dashboard-config rather than
 * being interpolated into the source.
 */
const ADMIN = JSON.parse(
  document.getElementById('admin-dashboard-config')?.textContent || '{}'
);
    /* ── tab switching ── */

    function adminCsrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.content || ADMIN.csrfToken;
    }

    function escapeAdminHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[char]));
    }

    function clearFeedbackBadge() {
      const badge = document.getElementById('admin-feedback-badge');
      if (!badge) return;
      localStorage.setItem('adminFeedbackSeenCount', badge.dataset.count || '0');
      badge.remove();
    }

    function restoreFeedbackBadgeState() {
      const badge = document.getElementById('admin-feedback-badge');
      if (!badge) return;
      const current = parseInt(badge.dataset.count || '0', 10);
      const seen = parseInt(localStorage.getItem('adminFeedbackSeenCount') || '0', 10);
      if (seen >= current) badge.remove();
    }

    /* Tabs are server-rendered: AdminController loads only the active tab's data
       and every tab control is a real link, so there is no client-side switch. */

    function enhanceServiceCards() {
      document.querySelectorAll('#tab-services form[action*="/services/"]').forEach(form => {
        const methodInput = form.querySelector('input[name="_method"]');
        if (!methodInput || methodInput.value.toUpperCase() !== 'PUT') return;

        const card = form.closest('div.border');
        if (!card || card.dataset.serviceEnhanced === 'true') return;
        card.dataset.serviceEnhanced = 'true';

        const name = form.querySelector('[name="name"]')?.value || 'Service';
        const feeValue = parseFloat(form.querySelector('[name="default_fee"]')?.value || '0');
        const isActive = !!form.querySelector('[name="is_active"]')?.checked;

        const detail = document.createElement('div');
        detail.className = 'service-detail-panel hidden border-t border-surface-100 bg-surface-50/60 p-4';
        while (card.firstChild) detail.appendChild(card.firstChild);

        const summary = document.createElement('div');
        summary.className = 'p-4 flex flex-col md:flex-row md:items-center gap-4';
        summary.innerHTML = `
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="w-10 h-10 rounded-lg border border-brand-100 bg-brand-50 text-brand-700 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.25 7.5 18 8.25l-.25-.75a2 2 0 0 0-1.25-1.25L15.75 6l.75-.25a2 2 0 0 0 1.25-1.25L18 3.75l.25.75a2 2 0 0 0 1.25 1.25l.75.25-.75.25a2 2 0 0 0-1.25 1.25Z"/></svg>
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="service-summary-name text-sm font-semibold text-surface-900 truncate"></h3>
                <span class="service-summary-active text-[10px] font-medium px-2 py-0.5 rounded border"></span>
              </div>
              <p class="text-xs text-surface-400 mt-0.5">Customer-bookable service</p>
            </div>
          </div>
          <div class="md:w-36 text-xs">
            <p class="text-[10px] text-surface-400 uppercase tracking-wider">Default Fee</p>
            <p class="service-summary-fee text-surface-900 font-semibold"></p>
          </div>
          <button type="button" class="service-detail-toggle inline-flex items-center justify-center gap-1.5 border border-surface-200 text-surface-600 hover:text-surface-900 hover:border-surface-300 rounded-lg px-3 py-2 text-xs font-medium transition-colors" aria-expanded="false">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0Z"/></svg>
            <span>Details / Edit</span>
          </button>
        `;

        summary.querySelector('.service-summary-name').textContent = name;
        summary.querySelector('.service-summary-fee').textContent = 'PHP ' + feeValue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const activeBadge = summary.querySelector('.service-summary-active');
        activeBadge.textContent = isActive ? 'Active' : 'Hidden';
        activeBadge.className += isActive ? ' bg-brand-50 text-brand-700 border-brand-100' : ' bg-surface-50 text-surface-400 border-surface-200';

        const toggle = summary.querySelector('.service-detail-toggle');
        toggle.addEventListener('click', () => {
          const isOpen = !detail.classList.contains('hidden');
          detail.classList.toggle('hidden', isOpen);
          toggle.setAttribute('aria-expanded', String(!isOpen));
          toggle.querySelector('span').textContent = isOpen ? 'Details / Edit' : 'Hide Details';
        });

        card.className = 'border border-surface-100 rounded-lg overflow-hidden hover:border-surface-200 transition-colors';
        card.appendChild(summary);
        card.appendChild(detail);
      });
    }

    function enhanceStatusControls() {
      document.querySelectorAll('.admin-status-form').forEach(form => {
        if (form.dataset.statusEnhanced === 'true') return;
        form.dataset.statusEnhanced = 'true';

        form.classList.add('hidden');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'inline-flex items-center gap-1 border border-surface-200 text-surface-500 hover:text-surface-900 hover:border-surface-300 rounded px-2.5 py-2 text-[10px] font-medium transition-colors';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = `
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.37.774.78.907.21.068.414.153.61.253.382.194.84.15 1.194-.094l.739-.51a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.51.738c-.245.354-.288.812-.094 1.194.1.196.185.4.253.61.133.41.483.71.907.78l.894.149c.542.09.94.56.94 1.11v1.093c0 .55-.398 1.02-.94 1.11l-.894.149c-.424.07-.774.37-.907.78a6.03 6.03 0 01-.253.61c-.194.382-.15.84.094 1.194l.51.739c.32.448.269 1.061-.12 1.45l-.774.773a1.125 1.125 0 01-1.45.12l-.738-.51c-.354-.245-.812-.288-1.194-.094a6.03 6.03 0 01-.61.253c-.41.133-.71.483-.78.907l-.149.894c-.09.542-.56.94-1.11.94h-1.093c-.55 0-1.02-.398-1.11-.94l-.149-.894c-.07-.424-.37-.774-.78-.907a6.03 6.03 0 01-.61-.253c-.382-.194-.84-.15-1.194.094l-.739.51a1.125 1.125 0 01-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.51-.738c.245-.354.288-.812.094-1.194a6.03 6.03 0 01-.253-.61c-.133-.41-.483-.71-.907-.78l-.894-.149A1.125 1.125 0 013 13.546v-1.093c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.774-.37.907-.78.068-.21.153-.414.253-.61.194-.382.15-.84-.094-1.194l-.51-.739a1.125 1.125 0 01.12-1.45l.774-.773a1.125 1.125 0 011.45-.12l.738.51c.354.245.812.288 1.194.094.196-.1.4-.185.61-.253.41-.133.71-.483.78-.907l.149-.894Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0Z"/></svg>
          <span>Manage</span>
        `;

        toggle.addEventListener('click', () => {
          if (form.dataset.detailUrl) {
            window.location.href = form.dataset.detailUrl;
            return;
          }

          const isOpen = !form.classList.contains('hidden');
          form.classList.toggle('hidden', isOpen);
          toggle.setAttribute('aria-expanded', String(!isOpen));
          toggle.querySelector('span').textContent = isOpen ? 'Manage' : 'Hide';
        });

        form.parentElement?.insertBefore(toggle, form);
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      restoreFeedbackBadgeState();
      enhanceServiceCards();
      enhanceStatusControls();

      // The active tab arrives already rendered from the server.
      if (ADMIN.activeTab === 'feedbacks') clearFeedbackBadge();

      const selectAll = document.getElementById('admin-select-all-orders');
      if (selectAll) {
        selectAll.addEventListener('change', function () {
          document.querySelectorAll('.admin-order-cb').forEach(cb => { cb.checked = selectAll.checked; });
        });
      }

      const bulkForm = document.getElementById('admin-bulk-orders-form');
      if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
          if (document.querySelectorAll('.admin-order-cb:checked').length === 0) {
            e.preventDefault();
            alert('Select at least one order.');
          }
        });
      }

      document.querySelectorAll('.admin-status-form').forEach(form => {
        form.addEventListener('submit', async (event) => {
          event.preventDefault();

          const button = form.querySelector('button[type="submit"], button:not([type])');
          const selects = Array.from(form.querySelectorAll('select'));
          const files = Array.from(form.querySelectorAll('input[type="file"]'));
          const originalLabel = button?.textContent.trim() || 'Save';
          const payload = new FormData(form);

          if (button) {
            button.textContent = button.dataset.savingLabel || 'Saving...';
            button.disabled = true;
          }
          selects.forEach(select => {
            select.disabled = true;
            select.classList.add('opacity-70');
          });
          files.forEach(file => {
            file.disabled = true;
            file.classList.add('opacity-70');
          });

          try {
            const response = await fetch(form.action, {
              method: 'POST',
              body: payload,
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
              },
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(data.message || 'Update failed.');
            }

            updateStatusRow(form, data);
            showAdminToast(data.message || 'Saved successfully.');
          } catch (error) {
            showAdminToast(error.message || 'Update failed. Please try again.', 'error');
          } finally {
            selects.forEach(select => {
              select.disabled = false;
              select.classList.remove('opacity-70');
            });
            files.forEach(file => {
              file.disabled = false;
              file.classList.remove('opacity-70');
            });
            if (button) {
              button.textContent = originalLabel;
              button.disabled = false;
            }
          }
        });
      });

      document.querySelectorAll('form button[data-saving-label]').forEach(button => {
        const form = button.closest('form');
        if (!form || form.classList.contains('admin-status-form')) return;
        form.addEventListener('submit', () => {
          button.dataset.originalLabel = button.textContent.trim();
          button.textContent = button.dataset.savingLabel || 'Saving...';
          button.disabled = true;
          button.classList.add('opacity-70', 'cursor-wait');
        }, { once: true });
      });
    });

    function showAdminToast(message, type = 'success') {
      const stack = document.getElementById('admin-toast-stack');
      if (!stack) return;

      const toast = document.createElement('div');
      toast.className = [
        'pointer-events-auto min-w-[240px] max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg transition-all',
        type === 'error'
          ? 'bg-red-50 border-red-100 text-red-700'
          : 'bg-brand-50 border-brand-100 text-brand-700'
      ].join(' ');
      toast.textContent = message;
      stack.appendChild(toast);

      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-6px)';
        setTimeout(() => toast.remove(), 180);
      }, 2800);
    }

    function paymentBadgeClass(paymentStatus) {
      const tones = {
        paid: 'bg-brand-50 text-brand-700 border-brand-100',
        pending_verification: 'bg-amber-50 text-amber-700 border-amber-100',
        rejected: 'bg-red-50 text-red-700 border-red-100',
        refunded: 'bg-purple-50 text-purple-700 border-purple-100',
        unpaid: 'bg-surface-50 text-surface-500 border-surface-200',
      };
      return 'admin-payment-badge text-[9px] font-semibold border px-1.5 py-0.5 rounded uppercase tracking-wide '
        + (tones[paymentStatus] || tones.unpaid);
    }

    function updateSelectPaymentStyle(select, paymentStatus) {
      select.classList.remove('text-brand-600', 'bg-brand-50', 'font-medium', 'text-surface-600');
      if (paymentStatus === 'paid') {
        select.classList.add('text-brand-600', 'bg-brand-50', 'font-medium');
      } else {
        select.classList.add('text-surface-600');
      }
    }

    function updateStatusRow(form, data) {
      const row = form.closest('tr');
      if (!row) return;

      const statusSelect = form.querySelector('select[name="status"]');
      const paymentSelect = form.querySelector('select[name="payment_status"]');
      const proofInput = form.querySelector('input[name="delivery_proof"]');
      const statusBadge = row.querySelector('.admin-status-badge');
      const paymentBadge = row.querySelector('.admin-payment-badge');

      if (statusSelect && data.status) statusSelect.value = data.status;
      if (paymentSelect && data.payment_status) {
        paymentSelect.value = data.payment_status;
        updateSelectPaymentStyle(paymentSelect, data.payment_status);
      }

      if (statusBadge && data.status) {
        const badgeMap = form.action.includes('/appointments/')
          ? APPT_STATUS_BADGE
          : STATUS_BADGE_CLASSES;
        statusBadge.textContent = data.status === 'delivered' && !data.customer_confirmed_at
          ? 'Delivered - Pending Confirmation'
          : (data.status_label || data.status.replace(/_/g, ' '));
        statusBadge.className = 'admin-status-badge px-2 py-0.5 rounded text-[10px] font-medium border '
          + (badgeMap[data.status] || 'bg-surface-50 text-surface-600 border-surface-200');
      }

      if (paymentBadge && data.payment_status) {
        paymentBadge.textContent = data.payment_label || (data.payment_status === 'paid' ? 'Paid' : 'Unpaid');
        paymentBadge.className = paymentBadgeClass(data.payment_status);
      }

      if (proofInput) proofInput.value = '';
      if (data.delivery_proof_url && !row.querySelector('.admin-proof-badge')) {
        const badgeWrap = paymentBadge?.parentElement;
        if (badgeWrap) {
          badgeWrap.insertAdjacentHTML('beforeend', '<span class="admin-proof-badge px-1.5 py-0.5 rounded text-[9px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-100 uppercase tracking-wide">Proof</span>');
        }
      }
    }

    /* ── Order Detail Modal ── */
    function safeAdminUrl(value) {
      if (!value) return '';
      try {
        const url = new URL(String(value), window.location.origin);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
      } catch {
        return '';
      }
    }

    const STATUS_BADGE_CLASSES = {
      pending:          'bg-amber-50 text-amber-700 border-amber-100',
      confirmed:        'bg-blue-50 text-blue-700 border-blue-100',
      out_for_delivery: 'bg-indigo-50 text-indigo-700 border-indigo-100',
      delivered:        'bg-emerald-50 text-emerald-700 border-emerald-100',
      completed:        'bg-brand-50 text-brand-700 border-brand-100',
      cancelled:        'bg-red-50 text-red-600 border-red-100',
    };

    let _lastAdminDialogTrigger = null;

    function openOrderDetail(order) {
      const modal = document.getElementById('admin-order-modal');
      _lastAdminDialogTrigger = document.activeElement;

      // Header
      document.getElementById('od-order-number').textContent = order.order_number;
      const statusBadge = document.getElementById('od-status-badge');
      statusBadge.textContent = order.status === 'delivered' && !order.customer_confirmed_at
        ? 'Delivered - Pending Confirmation'
        : order.status.replace(/_/g, ' ');
      statusBadge.className = 'text-[10px] font-semibold uppercase tracking-wide px-2.5 py-1 rounded border '
        + (STATUS_BADGE_CLASSES[order.status] || 'bg-surface-50 text-surface-600 border-surface-200');

      // Customer
      document.getElementById('od-avatar').textContent = (order.customer_name || '?')[0].toUpperCase();
      document.getElementById('od-customer-name').textContent  = order.customer_name || 'N/A';
      document.getElementById('od-customer-email').textContent = order.customer_email || '—';
      document.getElementById('od-customer-phone').textContent = order.customer_phone || '—';

      // Order meta
      document.getElementById('od-created-at').textContent = order.created_at || '—';
      document.getElementById('od-total').textContent = '₱' + order.total_amount;

      const pm = order.payment_method;
      const pmBadge = document.getElementById('od-payment-badge');
      pmBadge.textContent = pm === 'gcash' ? 'GCash' : 'Cash on Delivery';
      pmBadge.className = pm === 'gcash' ? 'font-medium text-sky-600' : 'font-medium text-amber-600';

      const psBadge = document.getElementById('od-payment-status-badge');
      const paymentStatus = order.payment_status || 'unpaid';
      psBadge.textContent = paymentStatus.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
      psBadge.className = paymentBadgeClass(paymentStatus).replace('admin-payment-badge', '');

      // Delivery
      const del = document.getElementById('od-delivery-content');
      if (order.delivery_method === 'pickup') {
        del.innerHTML = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border bg-purple-50 text-purple-700 border-purple-100 uppercase tracking-wide mb-1">Pick-up</span>'
          + '<p class="text-surface-400 text-xs">A. Arellano Ave. Mulawin, Orani, Philippines 2112</p>';
      } else {
        let html = '<span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border bg-blue-50 text-blue-700 border-blue-100 uppercase tracking-wide mb-2">Delivery</span><div class="space-y-0.5 text-xs">';
        if (order.delivery_name)    html += `<p><span class="text-surface-400">Name:</span> <span class="font-medium text-surface-800">${escapeAdminHtml(order.delivery_name)}</span></p>`;
        if (order.delivery_phone)   html += `<p><span class="text-surface-400">Phone:</span> <span class="text-surface-700">${escapeAdminHtml(order.delivery_phone)}</span></p>`;
        if (order.delivery_address) html += `<p><span class="text-surface-400">Address:</span> <span class="text-surface-700">${escapeAdminHtml(order.delivery_address)}${order.delivery_city ? ', ' + escapeAdminHtml(order.delivery_city) : ''}</span></p>`;
        if (order.delivery_notes)   html += `<p class="text-surface-400 italic">Notes: ${escapeAdminHtml(order.delivery_notes)}</p>`;
        html += '</div>';
        del.innerHTML = html;
      }

      const proof = document.getElementById('od-proof-content');
      const proofUrl = safeAdminUrl(order.delivery_proof_url);
      if (proofUrl) {
        const confirmed = order.customer_confirmed_at
          ? `<p class="text-brand-700 font-medium mt-1">Customer confirmed: ${escapeAdminHtml(order.customer_confirmed_at)}</p>`
          : '<p class="text-amber-700 font-medium mt-1">Waiting for customer confirmation.</p>';
        proof.innerHTML = `
          <a href="${escapeAdminHtml(proofUrl)}" target="_blank" rel="noopener noreferrer" class="inline-block rounded-lg overflow-hidden border border-surface-100 bg-white mb-2">
            <img src="${escapeAdminHtml(proofUrl)}" alt="Delivery proof" class="w-full max-h-56 object-cover">
          </a>
          <p class="text-xs text-surface-500">Delivered: ${escapeAdminHtml(order.delivered_at || 'Pending timestamp')}</p>
          ${confirmed}
        `;
      } else {
        proof.innerHTML = '<p class="text-xs text-surface-400">No delivery proof uploaded yet. Upload a photo when marking this order as delivered.</p>';
      }

      const cancelRecord = document.getElementById('od-cancel-record');
      const cancelContent = document.getElementById('od-cancel-content');
      if (order.status === 'cancelled') {
        cancelContent.innerHTML = `
          <p><span class="text-surface-400">Cancelled:</span> <span class="font-medium text-surface-800">${escapeAdminHtml(order.cancelled_at || 'Recorded')}</span></p>
          <p><span class="text-surface-400">Reason:</span> <span class="text-surface-700">${escapeAdminHtml(order.cancel_reason || 'No reason provided.')}</span></p>
        `;
        cancelRecord.classList.remove('hidden');
      } else {
        cancelContent.innerHTML = '';
        cancelRecord.classList.add('hidden');
      }

      // Items table
      const tbody = document.getElementById('od-items-tbody');
      tbody.innerHTML = '';
      const items = Array.isArray(order.items) ? order.items : [];
      let grandTotal = 0;
      if (items.length) {
        items.forEach(line => {
          const qty   = parseInt(line.qty ?? line.quantity ?? 1);
          const price = parseFloat(line.price ?? 0);
          const name  = line.name ?? 'Item';
          const sub   = qty * price;
          grandTotal += sub;
          tbody.insertAdjacentHTML('beforeend',
            `<tr class="hover:bg-surface-50">
              <td class="px-4 py-2.5 font-medium text-surface-800">${escapeAdminHtml(name)}</td>
              <td class="px-4 py-2.5 text-center text-surface-600">${qty}</td>
              <td class="px-4 py-2.5 text-right text-surface-600">₱${price.toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
              <td class="px-4 py-2.5 text-right font-semibold text-surface-900">₱${sub.toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
            </tr>`);
        });
      } else {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-4 text-center text-surface-400 text-xs">No line items recorded.</td></tr>';
      }
      document.getElementById('od-items-total').textContent = '₱' + grandTotal.toLocaleString('en-PH', {minimumFractionDigits:2});

      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      window.requestAnimationFrame(() => document.getElementById('od-dialog-panel')?.focus());
    }

    function closeOrderDetail() {
      const modal = document.getElementById('admin-order-modal');
      if (!modal || modal.classList.contains('hidden')) return;
      modal.classList.add('hidden');
      document.body.style.overflow = '';
      _lastAdminDialogTrigger?.focus?.();
    }

    /* ── Appointment Detail Modal ── */
    const APPT_STATUS_BADGE = {
      scheduled: 'bg-amber-50 text-amber-700 border-amber-100',
      confirmed:  'bg-blue-50 text-blue-700 border-blue-100',
      completed:  'bg-brand-50 text-brand-700 border-brand-100',
      cancelled:  'bg-red-50 text-red-600 border-red-100',
    };

    function openApptDetail(appt) {
      const modal = document.getElementById('admin-appt-modal');
      _lastAdminDialogTrigger = document.activeElement;

      document.getElementById('ad-service-label').textContent = appt.service;

      const badge = document.getElementById('ad-status-badge');
      badge.textContent = appt.status.replace(/_/g, ' ');
      badge.className = 'text-[10px] font-semibold uppercase tracking-wide px-2.5 py-1 rounded border '
        + (APPT_STATUS_BADGE[appt.status] || 'bg-surface-50 text-surface-600 border-surface-200');

      document.getElementById('ad-avatar').textContent = (appt.customer_name || '?')[0].toUpperCase();
      document.getElementById('ad-customer-name').textContent  = appt.customer_name || 'N/A';
      document.getElementById('ad-customer-email').textContent = appt.customer_email || '—';
      document.getElementById('ad-date').textContent = appt.appointment_at || '—';

      document.getElementById('ad-amount').textContent = 'PHP ' + (appt.appointment_amount || '0.00');

      const payBadge = document.getElementById('ad-payment-badge');
      const isPaid = appt.payment_status === 'paid';
      payBadge.textContent = isPaid ? 'Paid' : 'Unpaid';
      payBadge.className = isPaid
        ? 'text-[10px] font-semibold px-2 py-0.5 rounded border bg-brand-50 text-brand-700 border-brand-100'
        : 'text-[10px] font-semibold px-2 py-0.5 rounded border bg-surface-50 text-surface-500 border-surface-200';

      document.getElementById('ad-notes').textContent = appt.notes || 'No notes provided.';

      const feedbackSection = document.getElementById('ad-feedback-section');
      const starsEl  = document.getElementById('ad-feedback-stars');
      const commentEl = document.getElementById('ad-feedback-comment');
      if (appt.feedback_rating) {
        starsEl.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
          const s = document.createElement('span');
          s.textContent = '★';
          s.className = 'text-lg ' + (i <= appt.feedback_rating ? 'text-amber-400' : 'text-surface-200');
          starsEl.appendChild(s);
        }
        commentEl.textContent = appt.feedback_comment || '';
        feedbackSection.classList.remove('hidden');
      } else {
        feedbackSection.classList.add('hidden');
      }

      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      window.requestAnimationFrame(() => document.getElementById('ad-dialog-panel')?.focus());
    }

    function closeApptDetail() {
      const modal = document.getElementById('admin-appt-modal');
      if (!modal || modal.classList.contains('hidden')) return;
      modal.classList.add('hidden');
      document.body.style.overflow = '';
      _lastAdminDialogTrigger?.focus?.();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeOrderDetail(); closeApptDetail(); } });

    /* ── Messages Tab ── */
    let _activeConvoId  = null;

    // Real implementation is installed once the picker is wired up below; the
    // no-op keeps the reply handler safe if the elements are not on the page.
    let clearReplyAttachment = () => {};
    let _convoTimer     = null;
    const _renderedMsgIds = new Set();

    function openConversation(convoId, customerName) {
      _activeConvoId = convoId;
      _renderedMsgIds.clear();
      document.getElementById('tab-messages')?.classList.add('thread-open');

      // Keep the open thread in the URL so a refresh (or a link passed to a
      // colleague) lands back on the same conversation.
      const url = new URL(window.location.href);
      url.searchParams.set('tab', 'messages');
      url.searchParams.set('convo', convoId);
      window.history.replaceState({}, '', url);

      // Show thread panel, hide empty state
      document.getElementById('thread-empty').style.display  = 'none';
      const panel = document.getElementById('thread-panel');
      panel.style.display = 'flex';

      // Set header
      document.getElementById('thread-name').textContent   = customerName;
      document.getElementById('thread-avatar').textContent = customerName.charAt(0).toUpperCase();

      // Highlight active conversation
      document.querySelectorAll('.convo-btn').forEach(btn => {
        const isActive = parseInt(btn.dataset.convoId) === convoId;
        btn.classList.toggle('bg-brand-50', isActive);
        btn.classList.toggle('border-l-2', isActive);
        btn.classList.toggle('border-brand-500', isActive);
        if (isActive) {
          const badge = btn.querySelector('.bg-red-500');
          if (badge) badge.remove();
        }
      });

      // Wire reply via AJAX
      const form = document.getElementById('reply-form');
      form.onsubmit = async function (e) {
        e.preventDefault();
        const body  = document.getElementById('reply-body');
        const files = document.getElementById('reply-attachment');

        // An attachment on its own is a valid reply, so this cannot bail out
        // on empty text alone.
        if (!body.value.trim() && !files?.files[0]) return;

        const fd   = new FormData(form);
        const sbtn = form.querySelector('button[type=submit]');
        sbtn.disabled = true;
        try {
          // Ask for JSON so a rejected file comes back as a 422 we can read.
          // Without this Laravel answers with a redirect, fetch follows it, and
          // a failed upload looks like success.
          const response = await fetch(`${ADMIN.conversationsBase}/${convoId}/reply`, {
            method: 'POST',
            body: fd,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            },
          });

          if (!response.ok) {
            const problem = await response.json().catch(() => null);
            const detail = problem?.errors ? Object.values(problem.errors).flat()[0] : problem?.message;
            throw new Error(detail || 'Message could not be sent.');
          }

          body.value = '';
          body.style.height = 'auto';
          clearReplyAttachment();
          loadThread(convoId, true);
        } catch (error) {
          showAdminToast(error.message || 'Message could not be sent. Please try again.', 'error');
        }
        sbtn.disabled = false;
      };

      loadThread(convoId, true);

      clearInterval(_convoTimer);
      _convoTimer = setInterval(() => {
        if (_activeConvoId === convoId) loadThread(convoId, false);
      }, 5000);
    }

    function closeMobileConversation() {
      document.getElementById('tab-messages')?.classList.remove('thread-open');
      _activeConvoId = null;
      clearInterval(_convoTimer);

      const url = new URL(window.location.href);
      url.searchParams.delete('convo');
      window.history.replaceState({}, '', url);

      document.querySelector('.convo-btn')?.focus();
    }

    // Filenames come from customers, so they are set with textContent rather
    // than interpolated into markup.
    function buildAdminAttachment(att) {
      const link = document.createElement('a');
      link.href = att.url;
      link.target = '_blank';
      link.rel = 'noopener';

      if (att.is_image) {
        link.className = 'block overflow-hidden rounded-xl border border-surface-200 max-w-[220px]';
        const img = document.createElement('img');
        img.src = att.url;
        img.alt = att.name || '';
        img.loading = 'lazy';
        img.className = 'block w-full h-auto';
        link.appendChild(img);
        return link;
      }

      link.className = 'flex items-center gap-2.5 rounded-xl border border-surface-200 bg-white px-3 py-2.5 max-w-[220px] hover:border-brand-400 transition-colors';
      link.innerHTML = '<svg class="w-5 h-5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/></svg>';

      const meta = document.createElement('span');
      meta.className = 'min-w-0';
      const nameEl = document.createElement('span');
      nameEl.className = 'block truncate text-[12px] font-semibold text-surface-800';
      nameEl.textContent = att.name || 'Attachment';
      const sizeEl = document.createElement('span');
      sizeEl.className = 'block text-[10px] text-surface-400';
      sizeEl.textContent = att.size_label || '';
      meta.append(nameEl, sizeEl);
      link.appendChild(meta);
      return link;
    }

    function loadThread(convoId, initial) {
      const scrollToBottom = initial;
      fetch(`${ADMIN.conversationsBase}/${convoId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(data => {
        const box = document.getElementById('thread-messages');
        const wasAtBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;

        if (!data.messages || !data.messages.length) {
          box.innerHTML = '<p class="text-sm text-surface-400 text-center py-12">No messages yet.</p>';
          _renderedMsgIds.clear();
          return;
        }

        // Only append messages that are not on screen yet. Rebuilding the whole
        // thread every poll wiped text selection and fought the scroll position
        // while staff were reading.
        if (initial) {
          box.innerHTML = '';
          _renderedMsgIds.clear();
        }

        data.messages.forEach(msg => {
          if (_renderedMsgIds.has(msg.id)) return;
          _renderedMsgIds.add(msg.id);
          const wrap = document.createElement('div');
          wrap.className = `flex items-end gap-2 ${msg.is_admin ? 'justify-end' : 'justify-start'}`;

          if (!msg.is_admin) {
            const av = document.createElement('div');
            av.className = 'w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[10px] font-bold flex-shrink-0 mb-4';
            av.textContent = msg.sender.charAt(0).toUpperCase();
            wrap.appendChild(av);
          }

          const inner = document.createElement('div');
          inner.className = `max-w-[75%] flex flex-col ${msg.is_admin ? 'items-end' : 'items-start'} gap-0.5`;

          if (msg.attachment) inner.appendChild(buildAdminAttachment(msg.attachment));

          // Attachment-only messages carry no body, so skip the empty bubble.
          if (msg.body) {
            const bbl = document.createElement('div');
            bbl.className = msg.is_admin
              ? 'admin-chat-bubble admin-chat-bubble--mine'
              : 'admin-chat-bubble admin-chat-bubble--customer';
            bbl.textContent = msg.body;
            inner.appendChild(bbl);
          }

          const ts = document.createElement('span');
          ts.className = 'text-[10px] text-surface-400 px-1';
          ts.textContent = msg.created_at;
          inner.appendChild(ts);

          wrap.appendChild(inner);
          box.appendChild(wrap);
        });

        if (scrollToBottom || wasAtBottom) box.scrollTop = box.scrollHeight;
      })
      .catch(() => {});
    }

    // Auto-grow reply textarea
    document.addEventListener('DOMContentLoaded', () => {
      const rb = document.getElementById('reply-body');
      if (rb) {
        rb.addEventListener('input', () => {
          rb.style.height = 'auto';
          rb.style.height = Math.min(rb.scrollHeight, 120) + 'px';
        });
      }

      // --- Reply attachment picker ---------------------------------------
      const REPLY_MAX_BYTES = ADMIN.maxAttachmentKb * 1024;
      const rInput   = document.getElementById('reply-attachment');
      const rBtn     = document.getElementById('reply-attach-btn');
      const rPreview = document.getElementById('reply-attach-preview');
      const rThumb   = document.getElementById('reply-attach-thumb');
      const rIcon    = document.getElementById('reply-attach-icon');
      const rName    = document.getElementById('reply-attach-name');
      const rSize    = document.getElementById('reply-attach-size');
      let rThumbUrl = null;

      if (rInput && rBtn) {
        // Assigned to the outer binding so the reply handler in
        // openConversation() can reset the picker after a successful send.
        clearReplyAttachment = () => {
          rInput.value = '';
          rPreview.classList.add('hidden');
          rPreview.classList.remove('flex');
          if (rThumbUrl) { URL.revokeObjectURL(rThumbUrl); rThumbUrl = null; }
          rThumb.classList.add('hidden');
          rIcon.classList.add('hidden');
        };

        rBtn.addEventListener('click', () => rInput.click());
        document.getElementById('reply-attach-clear').addEventListener('click', clearReplyAttachment);

        rInput.addEventListener('change', () => {
          const file = rInput.files[0];
          if (!file) return clearReplyAttachment();

          if (file.size > REPLY_MAX_BYTES) {
            alert(`That file is larger than ${ADMIN.maxAttachmentMb} MB. Please choose a smaller one.`);
            return clearReplyAttachment();
          }

          rName.textContent = file.name;
          rSize.textContent = file.size >= 1048576
            ? (file.size / 1048576).toFixed(1) + ' MB'
            : Math.max(1, Math.round(file.size / 1024)) + ' KB';

          if (rThumbUrl) URL.revokeObjectURL(rThumbUrl);
          if (file.type.startsWith('image/')) {
            rThumbUrl = URL.createObjectURL(file);
            rThumb.src = rThumbUrl;
            rThumb.classList.remove('hidden');
            rIcon.classList.add('hidden');
          } else {
            rThumbUrl = null;
            rThumb.classList.add('hidden');
            rIcon.classList.remove('hidden');
          }

          rPreview.classList.remove('hidden');
          rPreview.classList.add('flex');
        });

      }

      // Reopen the conversation named in ?convo= after a reload.
      const wanted = new URLSearchParams(window.location.search).get('convo');
      if (!wanted) return;

      const btn = document.querySelector(`.convo-btn[data-convo-id="${CSS.escape(wanted)}"]`);
      if (btn) openConversation(parseInt(wanted, 10), btn.dataset.customerName);
    });

    // Stop polling a thread nobody is looking at.
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        clearInterval(_convoTimer);
      } else if (_activeConvoId !== null) {
        loadThread(_activeConvoId, false);
        clearInterval(_convoTimer);
        _convoTimer = setInterval(() => {
          if (_activeConvoId !== null) loadThread(_activeConvoId, false);
        }, 5000);
      }
    });

/*
 * The markup drives these through inline on* attributes, so they have to be
 * reachable as globals. Module scope is not.
 */
Object.assign(window, {
  adminCsrfToken,
  buildAdminAttachment,
  clearFeedbackBadge,
  closeApptDetail,
  closeMobileConversation,
  closeOrderDetail,
  enhanceServiceCards,
  enhanceStatusControls,
  escapeAdminHtml,
  loadThread,
  openApptDetail,
  openConversation,
  openOrderDetail,
  paymentBadgeClass,
  restoreFeedbackBadgeState,
  safeAdminUrl,
  showAdminToast,
  updateSelectPaymentStyle,
  updateStatusRow,
});
