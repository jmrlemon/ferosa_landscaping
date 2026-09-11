/**
 * Notification panel for the admin header.
 *
 * Loaded by every admin screen, so the bell behaves the same everywhere rather
 * than only on the dashboard. Reads its endpoints from #admin-header-config,
 * which admin.partials.workspace-header-actions renders alongside the markup.
 *
 * Deliberately self-contained: dashboard.js keeps its own copies of the two
 * helpers below because its order and message code uses them heavily, and
 * importing across entry points would couple the two bundles for ~10 lines.
 */
const CONFIG = JSON.parse(
  document.getElementById('admin-header-config')?.textContent || '{}'
);

let loaded = false;

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || CONFIG.csrfToken;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]));
}

function panelEl() {
  return document.getElementById('admin-notif-panel');
}

function toggleAdminNotifPanel() {
  const panel = panelEl();
  if (!panel) return;

  const isHidden = panel.classList.contains('hidden');
  panel.classList.toggle('hidden', !isHidden);
  document.getElementById('admin-notif-trigger')?.setAttribute('aria-expanded', String(isHidden));

  if (isHidden && !loaded) loadAdminNotifications();
}

function loadAdminNotifications() {
  fetch(CONFIG.notificationsUrl, {
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
  })
    .then((r) => r.json())
    .then((data) => {
      loaded = true;
      renderAdminNotifications(data.notifications || []);
    })
    .catch(() => {
      const list = document.getElementById('admin-notif-list');
      if (list) {
        list.innerHTML =
          '<div class="px-4 py-6 text-center text-xs text-surface-400">Could not load notifications.</div>';
      }
    });
}

function renderAdminNotifications(items) {
  const list = document.getElementById('admin-notif-list');
  if (!list) return;

  if (!items.length) {
    list.innerHTML =
      '<div class="px-4 py-6 text-center text-xs text-surface-400">No notifications yet.</div>';
    return;
  }

  list.innerHTML = items
    .map((n) => {
      const unread = !n.read_at;
      const message = escapeHtml(n.data?.message || 'Notification');
      const createdAt = escapeHtml(n.created_at || '');
      const url = String(n.data?.url || '').replace(/'/g, '&#39;');

      return `<button type="button" class="w-full text-left px-4 py-3 flex items-start gap-3 ${unread ? 'bg-brand-50' : ''} hover:bg-surface-50 transition-colors" onclick="readAdminNotification('${n.id}', '${url}', this)">
          <span class="flex-shrink-0 mt-1.5 w-2 h-2 rounded-full ${unread ? 'bg-red-500' : 'bg-surface-200'}"></span>
          <span class="flex-1 min-w-0">
            <span class="block text-xs text-surface-800 leading-snug">${message}</span>
            <span class="block text-[11px] text-surface-400 mt-0.5">${createdAt}</span>
          </span>
        </button>`;
    })
    .join('');
}

function clearAdminNotifCount() {
  document.getElementById('admin-notif-count')?.remove();
}

function readAdminNotification(id, url, el) {
  fetch(`${CONFIG.notificationsBase}/${id}/read`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
  }).finally(() => {
    el?.classList.remove('bg-brand-50');
    el?.querySelector('.bg-red-500')?.classList.replace('bg-red-500', 'bg-surface-200');
    clearAdminNotifCount();
    if (url) window.location = url;
  });
}

function markAdminNotificationsRead() {
  fetch(CONFIG.notificationsReadAllUrl, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
  }).then(() => {
    clearAdminNotifCount();
    document.querySelectorAll('#admin-notif-list .bg-brand-50').forEach((el) => el.classList.remove('bg-brand-50'));
    document
      .querySelectorAll('#admin-notif-list .bg-red-500')
      .forEach((el) => el.classList.replace('bg-red-500', 'bg-surface-200'));
  });
}

function closePanel() {
  panelEl()?.classList.add('hidden');
  document.getElementById('admin-notif-trigger')?.setAttribute('aria-expanded', 'false');
}

document.addEventListener('click', function (event) {
  const panel = panelEl();
  if (!panel || panel.classList.contains('hidden')) return;

  const wrapper = panel.closest('.relative');
  if (wrapper && !wrapper.contains(event.target)) closePanel();
});

document.addEventListener('keydown', function (event) {
  if (event.key !== 'Escape') return;
  if (panelEl()?.classList.contains('hidden') !== false) return;

  closePanel();
  document.getElementById('admin-notif-trigger')?.focus();
});

/* Inline on* attributes in the header markup need these as globals. */
Object.assign(window, {
  toggleAdminNotifPanel,
  loadAdminNotifications,
  renderAdminNotifications,
  clearAdminNotifCount,
  readAdminNotification,
  markAdminNotificationsRead,
});
