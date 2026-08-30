(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('rcm-admin-app');
    if (!root || !window.RoleChatApp) return;
    const app = new window.RoleChatApp(root, { mode: 'admin' });
    app.init();
    window.RoleChatAdminApp = app;
  });
})();
