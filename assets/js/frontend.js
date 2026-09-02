(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    const widget = document.getElementById('rcm-widget');
    const toggle = document.getElementById('rcm-widget-toggle');
    const panel = document.getElementById('rcm-widget-panel');
    const close = widget?.querySelector('.rcm-widget-close');
    const root = document.getElementById('rcm-frontend-app');
    if (!widget || !toggle || !panel || !root || !window.RoleChatApp) return;
	const app = new window.RoleChatApp(root, { mode: 'frontend', isVisible: () => !panel.hidden && !document.hidden });
    app.init();
    window.RoleChatFrontendApp = app;
	async function openWidget() {
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      widget.classList.add('is-open');
	  await app.refreshConversations();
	  if (app.state.activeConversationId) await app.fetchMessages(false, true);
    }
    function closeWidget() {
	  app.stopTyping();
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      widget.classList.remove('is-open');
    }
    toggle.addEventListener('click', () => panel.hidden ? openWidget() : closeWidget());
    close?.addEventListener('click', closeWidget);
    root.addEventListener('rcm:unread', function (event) {
      const badge = widget.querySelector('.rcm-widget-badge');
      const unread = Number(event.detail?.unread || 0);
      if (!badge) return;
      badge.textContent = String(Math.min(99, unread));
      badge.hidden = unread < 1;
    });
  });
})();
