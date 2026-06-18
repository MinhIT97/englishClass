/* global window, document */

/**
 * Lightweight toast/notification renderer. Reads window.__toasts
 * (set by <x-toast-host />) and renders each as a slide-in card.
 *
 * Public API:
 *   window.Toast.show('Saved!', 'success')
 *   window.Toast.show({ message: 'Error', type: 'error', duration: 8000 })
 */
(function () {
  'use strict';

  const ICONS = {
    success: '✅',
    error: '❌',
    warning: '⚠️',
    info: 'ℹ️',
  };

  const COLORS = {
    success: { border: 'var(--accent)', bg: 'rgba(34,197,94,0.08)' },
    error:   { border: '#ef4444',       bg: 'rgba(239,68,68,0.08)' },
    warning: { border: '#f59e0b',       bg: 'rgba(245,158,11,0.08)' },
    info:    { border: 'var(--primary)', bg: 'rgba(99,102,241,0.08)' },
  };

  function ensureHost() {
    let host = document.getElementById('toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toast-host';
      host.setAttribute('aria-live', 'polite');
      host.setAttribute('aria-atomic', 'true');
      host.style.cssText =
        'position:fixed;top:1rem;right:1rem;z-index:9999;' +
        'display:flex;flex-direction:column;gap:0.5rem;max-width:360px;';
      document.body.appendChild(host);
    }
    return host;
  }

  function show(input, type = 'info', duration = 4000) {
    const payload = typeof input === 'string' ? { message: input, type, duration } : { type: 'info', duration: 4000, ...input };
    const host = ensureHost();

    const card = document.createElement('div');
    const palette = COLORS[payload.type] || COLORS.info;
    card.style.cssText =
      'display:flex;align-items:flex-start;gap:0.5rem;padding:0.75rem 1rem;' +
      'border-radius:10px;border:1px solid ' + palette.border + ';' +
      'background:' + palette.bg + ';color:var(--text-main);' +
      'box-shadow:0 4px 20px rgba(0,0,0,0.08);' +
      'animation:toast-slide-in 0.25s ease-out;font-size:0.875rem;';

    card.innerHTML =
      '<span style="font-size:1.1rem;line-height:1">' + (ICONS[payload.type] || ICONS.info) + '</span>' +
      '<span style="flex:1">' + escapeHtml(payload.message) + '</span>' +
      '<button type="button" aria-label="Dismiss" style="background:none;border:none;color:inherit;cursor:pointer;padding:0;line-height:1">' +
        '<span aria-hidden="true">&times;</span>' +
      '</button>';

    const dismiss = () => {
      card.style.animation = 'toast-slide-out 0.2s ease-in forwards';
      setTimeout(() => card.remove(), 200);
    };
    card.querySelector('button').addEventListener('click', dismiss);
    host.appendChild(card);
    if (payload.duration > 0) {
      setTimeout(dismiss, payload.duration);
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  // Inject keyframes once
  if (!document.getElementById('toast-keyframes')) {
    const style = document.createElement('style');
    style.id = 'toast-keyframes';
    style.textContent =
      '@keyframes toast-slide-in { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }' +
      '@keyframes toast-slide-out { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }' +
      '@media (prefers-reduced-motion: reduce) { .toast-card { animation: none !important; } }';
    document.head.appendChild(style);
  }

  // Drain any queued toasts from the server side.
  function drain() {
    if (Array.isArray(window.__toasts) && window.__toasts.length) {
      window.__toasts.forEach(t => show(t));
      window.__toasts = [];
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', drain);
  } else {
    drain();
  }

  window.Toast = { show };
})();