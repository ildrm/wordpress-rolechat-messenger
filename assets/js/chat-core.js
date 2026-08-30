(function () {
  'use strict';

  const cfg = window.RCM_CONFIG || {};
  const labels = cfg.labels || {};

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fmtTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const today = new Date();
    const sameDay = d.toDateString() === today.toDateString();
    return sameDay
      ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      : d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  function fmtFullTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleString();
  }

  function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(v => v.charAt(0).toUpperCase()).join('');
  }

  function statusIcon(status) {
    if (status === 'read') return '<span class="rcm-checks rcm-checks-read">✓✓</span>';
    if (status === 'delivered') return '<span class="rcm-checks">✓✓</span>';
    return '<span class="rcm-checks">✓</span>';
  }

  class RoleChatApp {
    constructor(root, options = {}) {
      this.root = root;
      this.mode = options.mode || cfg.mode || 'admin';
      this.state = {
        user: null,
        conversations: [],
        contacts: [],
        categories: [],
        blockedUsers: [],
        features: {},
        limits: {},
        activeConversationId: 0,
        messages: [],
        hasMore: false,
        view: 'chats',
        replyTo: null,
        pendingAttachments: [],
        loadingMessages: false,
        search: '',
        lastNotifiedMessageId: 0,
      };
      this.poller = null;
      this.presenceTimer = null;
      this.typingTimer = null;
      this.typingStopTimer = null;
      this.lastTypingPing = 0;
      this.bound = false;
    }

    async init() {
      try {
        const data = await this.api('bootstrap');
        this.state.user = data.user;
        this.state.conversations = data.conversations || [];
        this.state.contacts = data.contacts || [];
        this.state.categories = data.categories || [];
        this.state.blockedUsers = data.blocked_users || [];
        this.state.features = data.features || {};
        this.state.limits = data.limits || {};
        this.renderShell();
        this.bindEvents();
        this.renderSidebar();
        this.renderEmpty();
        this.emitUnread();
        this.startPolling();
        this.heartbeatPresence();
      } catch (error) {
        this.renderFatal(error.message || labels.error || 'Error');
      }
    }

    async api(path, options = {}) {
      const method = options.method || 'GET';
      const headers = Object.assign({ 'X-WP-Nonce': cfg.nonce }, options.headers || {});
      const init = { method, credentials: 'same-origin', headers };
      if (options.body instanceof FormData) {
        init.body = options.body;
      } else if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(options.body);
      }
      const response = await fetch(String(cfg.restUrl || '').replace(/\/$/, '') + '/' + path.replace(/^\//, ''), init);
      let data = {};
      try { data = await response.json(); } catch (e) {}
      if (!response.ok) {
        const message = data && data.message ? data.message : (labels.error || 'Request failed');
        throw new Error(message);
      }
      return data;
    }

    renderShell() {
      const frontend = this.mode === 'frontend';
      this.root.innerHTML = `
        <div class="rcm-shell ${frontend ? 'rcm-shell-frontend' : ''}">
          <aside class="rcm-sidebar">
            <div class="rcm-sidebar-head">
              <div class="rcm-me">
                ${this.avatarHtml(this.state.user, 'sm')}
                <div><strong>${esc(this.state.user?.name || '')}</strong>${this.state.features.presence ? `<select class="rcm-presence-select" data-role="presence-select" aria-label="Presence status"><option value="online" ${this.state.user?.presence === 'online' ? 'selected' : ''}>${esc(labels.online || 'Online')}</option><option value="away" ${this.state.user?.presence === 'away' ? 'selected' : ''}>${esc(labels.away || 'Away')}</option><option value="busy" ${this.state.user?.presence === 'busy' ? 'selected' : ''}>${esc(labels.busy || 'Busy')}</option><option value="dnd" ${this.state.user?.presence === 'dnd' ? 'selected' : ''}>${esc(labels.dnd || 'Do not disturb')}</option><option value="offline" ${this.state.user?.presence === 'offline' ? 'selected' : ''}>${esc(labels.offline || 'Offline')}</option></select>` : '<span></span>'}</div>
              </div>
              <div class="rcm-head-actions">
                <button type="button" class="rcm-icon-btn" data-action="new-chat" title="${esc(labels.newChat || 'New chat')}">＋</button>
                ${this.state.features.browser_notifications ? `<button type="button" class="rcm-icon-btn" data-action="request-notifications" title="Notifications">♢</button>` : ''}
                ${this.state.features.can_create_group ? `<button type="button" class="rcm-icon-btn" data-action="new-group" title="${esc(labels.newGroup || 'New group')}">◫</button>` : ''}
              </div>
            </div>
            <div class="rcm-search-box"><span>⌕</span><input type="search" data-role="conversation-search" placeholder="${esc(labels.search || 'Search')}"></div>
            <nav class="rcm-nav-tabs">
              <button type="button" data-view="chats" class="is-active">Chats</button>
              <button type="button" data-view="contacts">${esc(labels.contacts || 'Contacts')}</button>
              <button type="button" data-view="archived">${esc(labels.archived || 'Archived')}</button>
            </nav>
            <div class="rcm-sidebar-list" data-role="sidebar-list"></div>
          </aside>
          <main class="rcm-chat-pane" data-role="chat-pane"></main>
          <aside class="rcm-details-pane" data-role="details-pane" hidden></aside>
        </div>
        <div class="rcm-toast-stack" data-role="toasts" aria-live="polite"></div>
        <div class="rcm-modal-layer" data-role="modal-layer"></div>
      `;
    }

    bindEvents() {
      if (this.bound) return;
      this.bound = true;
      this.root.addEventListener('click', (event) => this.handleClick(event));
      this.root.addEventListener('input', (event) => this.handleInput(event));
      this.root.addEventListener('keydown', (event) => this.handleKeydown(event));
      this.root.addEventListener('change', (event) => this.handleChange(event));
    }

    async handleClick(event) {
      const target = event.target.closest('[data-action], [data-conversation-id], [data-contact-user], [data-view]');
      if (!target) return;

      if (target.dataset.view) {
        this.state.view = target.dataset.view;
        this.root.querySelectorAll('.rcm-nav-tabs button').forEach(b => b.classList.toggle('is-active', b.dataset.view === this.state.view));
        this.renderSidebar();
        return;
      }

      if (target.dataset.conversationId) {
        await this.openConversation(Number(target.dataset.conversationId));
        return;
      }

      if (target.dataset.contactUser) {
        const layer = this.root.querySelector('[data-role="modal-layer"]');
        const groupId = Number(layer?.dataset.addToGroup || 0);
        if (groupId) {
          try {
            await this.api(`conversations/${groupId}/members`, { method: 'POST', body: { user_id: Number(target.dataset.contactUser) } });
            this.closeModal();
            await this.refreshConversations();
            const pane = this.root.querySelector('[data-role="details-pane"]');
            if (pane && !pane.hidden && this.activeConversation()) pane.innerHTML = this.detailsHtml(this.activeConversation());
          } catch (error) { this.toast(error.message, 'error'); }
        } else {
          await this.startDirect(Number(target.dataset.contactUser));
        }
        return;
      }

      const action = target.dataset.action;
      switch (action) {
        case 'new-chat': this.openUserPicker(false); break;
        case 'new-group': this.openUserPicker(true); break;
        case 'close-modal': this.closeModal(); break;
        case 'back-list': this.root.classList.remove('rcm-has-active-chat'); this.state.activeConversationId = 0; this.renderEmpty(); break;
        case 'send-message': await this.sendMessage(); break;
        case 'attach': this.root.querySelector('[data-role="attachment-input"]')?.click(); break;
        case 'cancel-reply': this.state.replyTo = null; this.renderComposerState(); break;
        case 'reply': this.setReply(Number(target.dataset.messageId)); break;
        case 'edit': await this.editMessage(Number(target.dataset.messageId)); break;
        case 'delete': await this.deleteMessage(Number(target.dataset.messageId)); break;
        case 'react': await this.react(Number(target.dataset.messageId), target.dataset.reaction); break;
        case 'reaction-menu': this.toggleReactionMenu(Number(target.dataset.messageId)); break;
        case 'report': await this.reportMessage(Number(target.dataset.messageId)); break;
        case 'forward': this.openForwardPicker(Number(target.dataset.messageId)); break;
        case 'pin': await this.updateConversationState({ is_pinned: !this.activeConversation()?.is_pinned }); break;
        case 'archive': await this.updateConversationState({ is_archived: !this.activeConversation()?.is_archived }); break;
        case 'mute': await this.toggleMute(); break;
        case 'search-messages': this.openMessageSearch(); break;
        case 'details': this.toggleDetails(); break;
        case 'add-contact': await this.addActiveContact(); break;
        case 'remove-contact': await this.removeActiveContact(); break;
        case 'block-user': await this.blockActiveUser(); break;
        case 'unblock-user': await this.unblockActiveUser(); break;
        case 'create-category': this.createCategoryPrompt(); break;
        case 'rename-category': await this.renameCategory(Number(target.dataset.categoryId)); break;
        case 'delete-category': await this.deleteCategory(Number(target.dataset.categoryId)); break;
        case 'remove-pending-attachment': this.removePendingAttachment(Number(target.dataset.attachmentId)); break;
        case 'add-group-member': this.openAddGroupMember(); break;
        case 'remove-group-member': await this.removeGroupMember(Number(target.dataset.userId)); break;
        case 'leave-group': await this.leaveGroup(); break;
        case 'request-notifications': this.requestNotifications(); break;
      }
    }

    handleInput(event) {
      if (event.target.matches('[data-role="conversation-search"]')) {
        this.state.search = event.target.value || '';
        this.renderSidebar();
      }
      if (event.target.matches('[data-role="composer"]')) {
        this.resizeComposer(event.target);
        this.signalTyping();
      }
      if (event.target.matches('[data-role="user-search"]')) {
        clearTimeout(this.userSearchTimer);
        this.userSearchTimer = setTimeout(() => this.loadUsersIntoModal(event.target.value || ''), 250);
      }
      if (event.target.matches('[data-role="message-search"]')) {
        clearTimeout(this.messageSearchTimer);
        this.messageSearchTimer = setTimeout(() => this.performMessageSearch(event.target.value || ''), 300);
      }
    }

    handleKeydown(event) {
      if (event.target.matches('[data-role="composer"]') && event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        this.sendMessage();
      }
    }

    async handleChange(event) {
      if (event.target.matches('[data-role="presence-select"]')) {
        const status = event.target.value || 'online';
        try {
          const data = await this.api('presence', { method: 'POST', body: { status } });
          if (this.state.user) this.state.user.presence = data.presence || status;
        } catch (error) { this.toast(error.message, 'error'); }
        return;
      }
      if (event.target.matches('[data-role="attachment-input"]')) {
        const files = Array.from(event.target.files || []).slice(0, 10);
        event.target.value = '';
        for (const file of files) {
          await this.uploadAttachment(file);
        }
      }
    }

    renderSidebar() {
      const host = this.root.querySelector('[data-role="sidebar-list"]');
      if (!host) return;
      const q = this.state.search.trim().toLowerCase();

      if (this.state.view === 'contacts') {
        this.renderContacts(host, q);
        return;
      }

      const archived = this.state.view === 'archived';
      let conversations = this.state.conversations.filter(c => Boolean(c.is_archived) === archived);
      if (q) conversations = conversations.filter(c => String(c.title || '').toLowerCase().includes(q) || String(c.last_message?.content || '').toLowerCase().includes(q));

      if (!conversations.length) {
        host.innerHTML = `<div class="rcm-sidebar-empty"><span>☁</span><p>${esc(labels.noConversations || 'No conversations yet.')}</p>${archived ? '' : `<button type="button" class="rcm-text-btn" data-action="new-chat">${esc(labels.newChat || 'New chat')}</button>`}</div>`;
        return;
      }

      host.innerHTML = conversations.map(c => {
        const active = c.id === this.state.activeConversationId ? 'is-active' : '';
        const typing = c.typing && c.typing.length ? `${esc(c.typing[0].name)} ${esc(labels.typing || 'typing…')}` : '';
        const preview = typing || (c.last_message?.content || '');
        return `<button type="button" class="rcm-conversation ${active}" data-conversation-id="${Number(c.id)}">
          <span class="rcm-conv-avatar">${this.avatarHtml({ name: c.title, avatar: c.avatar }, 'md')}${this.conversationPresenceDot(c)}</span>
          <span class="rcm-conv-body"><span class="rcm-conv-top"><strong>${esc(c.title)}</strong><time>${esc(fmtTime(c.last_message_at))}</time></span><span class="rcm-conv-bottom"><span class="${typing ? 'rcm-typing-text' : ''}">${esc(preview)}</span><span class="rcm-conv-badges">${c.is_pinned ? '<i title="Pinned">⌖</i>' : ''}${c.unread ? `<b>${Math.min(99, Number(c.unread))}</b>` : ''}</span></span></span>
        </button>`;
      }).join('');
    }

    renderContacts(host, q) {
      const categories = [{ id: 0, name: 'All contacts' }, ...this.state.categories];
      let html = `<div class="rcm-contacts-toolbar"><button type="button" class="rcm-text-btn" data-action="new-chat">＋ ${esc(labels.newChat || 'New chat')}</button><button type="button" class="rcm-text-btn" data-action="create-category">＋ Category</button></div>`;
      categories.forEach(category => {
        let contacts = this.state.contacts.filter(c => category.id === 0 || Number(c.category_id) === Number(category.id));
        if (q) contacts = contacts.filter(c => String(c.name || '').toLowerCase().includes(q));
        if (!contacts.length && category.id !== 0) return;
        html += `<div class="rcm-contact-group"><div class="rcm-contact-group-title"><strong>${esc(category.name)}</strong>${category.id ? `<span class="rcm-category-actions"><button type="button" data-action="rename-category" data-category-id="${Number(category.id)}" aria-label="Rename category">✎</button><button type="button" data-action="delete-category" data-category-id="${Number(category.id)}" aria-label="Delete category">×</button></span>` : ''}</div>`;
        html += contacts.length ? contacts.map(contact => `<button type="button" class="rcm-contact-row" data-contact-user="${Number(contact.id)}">${this.avatarHtml(contact, 'sm')}<span><strong>${esc(contact.name)}</strong><small>${esc(this.userSecondary(contact))}</small></span></button>`).join('') : `<div class="rcm-mini-empty">No contacts</div>`;
        html += '</div>';
      });
      host.innerHTML = html;
    }

    renderEmpty() {
      const pane = this.root.querySelector('[data-role="chat-pane"]');
      if (!pane) return;
      pane.innerHTML = `<div class="rcm-empty-chat"><div class="rcm-empty-icon">✦</div><h2>${esc(labels.newMessage || 'New message')}</h2><p>${esc(labels.selectConversation || 'Select a conversation to start messaging.')}</p><button type="button" class="rcm-primary-btn" data-action="new-chat">${esc(labels.newChat || 'New chat')}</button></div>`;
      this.root.querySelector('[data-role="details-pane"]')?.setAttribute('hidden', 'hidden');
    }

    async openConversation(id) {
      this.state.activeConversationId = id;
      this.root.classList.add('rcm-has-active-chat');
      this.renderSidebar();
      this.renderChatFrame();
      await this.fetchMessages(false, true);
    }

    renderChatFrame() {
      const c = this.activeConversation();
      const pane = this.root.querySelector('[data-role="chat-pane"]');
      if (!c || !pane) return;
      const subtitle = this.conversationSubtitle(c);
      const muted = c.muted_until && new Date(c.muted_until + 'Z').getTime() > Date.now();
      pane.innerHTML = `
        <header class="rcm-chat-header">
          <button type="button" class="rcm-icon-btn rcm-back-btn" data-action="back-list" aria-label="Back">‹</button>
          <button type="button" class="rcm-chat-person" data-action="details">${this.avatarHtml({ name: c.title, avatar: c.avatar }, 'md')}<span><strong>${esc(c.title)}</strong><small data-role="chat-subtitle">${esc(subtitle)}</small></span></button>
          <div class="rcm-chat-actions">
            <button type="button" class="rcm-icon-btn" data-action="search-messages" title="Search messages">⌕</button>
            <button type="button" class="rcm-icon-btn" data-action="pin" title="${esc(c.is_pinned ? labels.unpin : labels.pin)}">${c.is_pinned ? '⌖' : '⌖'}</button>
            <button type="button" class="rcm-icon-btn" data-action="mute" title="${esc(muted ? labels.unmute : labels.mute)}">${muted ? '🔕' : '🔔'}</button>
            <button type="button" class="rcm-icon-btn" data-action="details" title="Details">⋮</button>
          </div>
        </header>
        <div class="rcm-messages-wrap">
          <button type="button" class="rcm-load-more" data-action="load-more" hidden>Load earlier messages</button>
          <div class="rcm-messages" data-role="messages"><div class="rcm-message-loader"><span class="rcm-spinner"></span></div></div>
          <div class="rcm-typing-line" data-role="typing-line"></div>
        </div>
        <footer class="rcm-composer-wrap">
          <div class="rcm-reply-banner" data-role="reply-banner" hidden></div>
          <div class="rcm-pending-attachments" data-role="pending-attachments"></div>
          <div class="rcm-composer-row">
            ${this.state.features.attachments ? `<button type="button" class="rcm-icon-btn rcm-attach-btn" data-action="attach" title="${esc(labels.attach || 'Attach')}">⌕</button><input type="file" data-role="attachment-input" multiple hidden>` : ''}
            <textarea data-role="composer" rows="1" maxlength="10000" placeholder="${esc(labels.typeMessage || 'Write a message…')}"></textarea>
            <button type="button" class="rcm-send-btn" data-action="send-message" title="${esc(labels.send || 'Send')}">➤</button>
          </div>
          <div class="rcm-composer-hint">Enter to send · Shift+Enter for a new line</div>
        </footer>`;
      const loadMore = pane.querySelector('[data-action="load-more"]');
      if (loadMore) loadMore.addEventListener('click', () => this.fetchMessages(true, false));
      this.renderComposerState();
    }

    async fetchMessages(older = false, initial = false) {
      const id = this.state.activeConversationId;
      if (!id || this.state.loadingMessages) return;
      this.state.loadingMessages = true;
      try {
        const before = older && this.state.messages.length ? this.state.messages[0].id : 0;
        const data = await this.api(`conversations/${id}/messages?limit=50${before ? `&before_id=${before}` : ''}`);
        const incoming = data.messages || [];
        if (older) {
          const known = new Set(this.state.messages.map(m => m.id));
          this.state.messages = [...incoming.filter(m => !known.has(m.id)), ...this.state.messages];
        } else {
          const previousMax = this.state.messages.length ? Math.max(...this.state.messages.map(m => m.id)) : 0;
          const newForeign = incoming.filter(m => m.id > previousMax && !m.own);
          if (initial || !this.state.messages.length) {
            this.state.messages = incoming;
          } else {
            const merged = new Map(this.state.messages.map(m => [Number(m.id), m]));
            incoming.forEach(m => merged.set(Number(m.id), m));
            this.state.messages = Array.from(merged.values()).sort((a, b) => Number(a.id) - Number(b.id));
          }
          if (!initial && newForeign.length) this.notifyNewMessages(newForeign);
        }
        this.state.hasMore = Boolean(data.has_more);
        this.renderMessages(older);
        this.renderTyping(data.typing || []);
        const maxId = this.state.messages.length ? Math.max(...this.state.messages.map(m => m.id)) : 0;
        if (maxId) await this.api(`conversations/${id}/read`, { method: 'POST', body: { message_id: maxId } });
      } catch (error) {
        this.toast(error.message, 'error');
      } finally {
        this.state.loadingMessages = false;
      }
    }

    renderMessages(preserveScroll = false) {
      const host = this.root.querySelector('[data-role="messages"]');
      if (!host) return;
      const oldHeight = host.scrollHeight;
      if (!this.state.messages.length) {
        host.innerHTML = `<div class="rcm-chat-empty"><span>✦</span><p>No messages yet. Start the conversation.</p></div>`;
      } else {
        let lastDate = '';
        host.innerHTML = this.state.messages.map(message => {
          const d = new Date(message.created_at);
          const dateKey = Number.isNaN(d.getTime()) ? '' : d.toDateString();
          let separator = '';
          if (dateKey && dateKey !== lastDate) {
            separator = `<div class="rcm-date-separator"><span>${esc(d.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }))}</span></div>`;
            lastDate = dateKey;
          }
          return separator + this.messageHtml(message);
        }).join('');
      }
      const loadMore = this.root.querySelector('[data-action="load-more"]');
      if (loadMore) loadMore.hidden = !this.state.hasMore;
      if (preserveScroll) host.scrollTop = host.scrollHeight - oldHeight;
      else host.scrollTop = host.scrollHeight;
    }

    messageHtml(message) {
      if (message.deleted) {
        return `<article class="rcm-message ${message.own ? 'is-own' : 'is-other'} is-deleted" data-message-id="${message.id}"><div class="rcm-bubble"><em>${esc(labels.messageDeleted || 'Message deleted')}</em><span class="rcm-message-meta"><time>${esc(fmtTime(message.created_at))}</time></span></div></article>`;
      }
      const senderName = message.sender?.name || 'User';
      const reply = message.reply_to ? `<button type="button" class="rcm-replied-message" data-message-jump="${Number(message.reply_to.id)}"><strong>${esc(message.reply_to.sender)}</strong><span>${esc(message.reply_to.content)}</span></button>` : '';
      const text = message.content ? `<div class="rcm-message-text">${esc(message.content).replace(/\n/g, '<br>')}</div>` : '';
      const attachments = (message.attachments || []).map(a => a.is_image
        ? `<a class="rcm-image-attachment" href="${esc(a.url)}" target="_blank" rel="noopener"><img src="${esc(a.thumbnail_url || a.url)}" alt="${esc(a.name)}" loading="lazy"></a>`
        : `<a class="rcm-file-attachment" href="${esc(a.url)}" target="_blank" rel="noopener"><span>▧</span><div><strong>${esc(a.name)}</strong><small>${esc(this.formatBytes(a.size))}</small></div></a>`
      ).join('');
      const reactions = (message.reactions || []).map(r => `<button type="button" class="rcm-reaction-chip ${r.mine ? 'is-mine' : ''}" data-action="react" data-message-id="${message.id}" data-reaction="${esc(r.reaction)}">${esc(r.reaction)} <span>${Number(r.count)}</span></button>`).join('');
      const senderLabel = !message.own && this.activeConversation()?.type === 'group' ? `<div class="rcm-sender-name">${esc(senderName)}</div>` : '';
      const edited = message.edited_at ? `<span>${esc(labels.edited || 'edited')}</span>` : '';
      const reactionMenu = this.state.features.reactions ? `<div class="rcm-reaction-menu" data-reaction-menu="${message.id}" hidden>${['👍','❤️','😂','😮','😢','👏','👎'].map(r => `<button type="button" data-action="react" data-message-id="${message.id}" data-reaction="${r}">${r}</button>`).join('')}</div>` : '';
      const actions = `<div class="rcm-message-actions">
        ${this.state.features.reactions ? `<button type="button" data-action="reaction-menu" data-message-id="${message.id}" title="React">☺</button>` : ''}
        <button type="button" data-action="reply" data-message-id="${message.id}" title="${esc(labels.reply || 'Reply')}">↩</button>
        ${this.state.features.forward ? `<button type="button" data-action="forward" data-message-id="${message.id}" title="Forward">➜</button>` : ''}
        ${message.own && this.state.features.edit ? `<button type="button" data-action="edit" data-message-id="${message.id}" title="${esc(labels.edit || 'Edit')}">✎</button>` : ''}
        ${message.own && this.state.features.delete ? `<button type="button" data-action="delete" data-message-id="${message.id}" title="${esc(labels.delete || 'Delete')}">×</button>` : ''}
        ${!message.own ? `<button type="button" data-action="report" data-message-id="${message.id}" title="${esc(labels.report || 'Report')}">!</button>` : ''}
      </div>`;
      const mentioned = (message.mention_user_ids || []).map(Number).includes(Number(this.state.user?.id)) ? ' is-mentioned' : '';
      return `<article class="rcm-message ${message.own ? 'is-own' : 'is-other'}${mentioned}" data-message-id="${message.id}">
        ${!message.own ? this.avatarHtml(message.sender, 'xs') : ''}
        <div class="rcm-message-stack">${senderLabel}<div class="rcm-bubble">${reply}${attachments}${text}<span class="rcm-message-meta"><time title="${esc(fmtFullTime(message.created_at))}">${esc(fmtTime(message.created_at))}</time>${edited}${message.own ? statusIcon(message.status) : ''}</span>${reactionMenu}</div>${actions}${reactions ? `<div class="rcm-reactions">${reactions}</div>` : ''}</div>
      </article>`;
    }

    renderTyping(users) {
      const line = this.root.querySelector('[data-role="typing-line"]');
      const subtitle = this.root.querySelector('[data-role="chat-subtitle"]');
      if (!line) return;
      if (users && users.length) {
        const text = users.length === 1 ? `${users[0].name} ${labels.typing || 'typing…'}` : `${users.slice(0,2).map(u => u.name).join(', ')} ${labels.typing || 'typing…'}`;
        line.textContent = text;
        line.classList.add('is-visible');
        if (subtitle) subtitle.textContent = text;
      } else {
        line.textContent = '';
        line.classList.remove('is-visible');
        if (subtitle && this.activeConversation()) subtitle.textContent = this.conversationSubtitle(this.activeConversation());
      }
    }

    async sendMessage() {
      const c = this.activeConversation();
      const input = this.root.querySelector('[data-role="composer"]');
      if (!c || !input) return;
      const content = input.value.trim();
      if (!content && !this.state.pendingAttachments.length) return;
      const button = this.root.querySelector('[data-action="send-message"]');
      if (button) button.disabled = true;
      try {
        const data = await this.api(`conversations/${c.id}/messages`, {
          method: 'POST',
          body: {
            content,
            reply_to_id: this.state.replyTo?.id || 0,
            attachment_ids: this.state.pendingAttachments.map(a => a.id),
          },
        });
        input.value = '';
        this.resizeComposer(input);
        this.state.replyTo = null;
        this.state.pendingAttachments = [];
        this.renderComposerState();
        if (data.message) {
          this.state.messages.push(data.message);
          this.renderMessages();
        }
        this.stopTyping();
        await this.refreshConversations();
      } catch (error) {
        this.toast(error.message, 'error');
      } finally {
        if (button) button.disabled = false;
      }
    }

    async uploadAttachment(file) {
      const max = Number(this.state.limits.max_attachment_mb || 10) * 1024 * 1024;
      if (file.size > max) {
        this.toast(`File exceeds ${this.state.limits.max_attachment_mb || 10} MB.`, 'error');
        return;
      }
      const tempId = 'upload-' + Math.random().toString(36).slice(2);
      this.state.pendingAttachments.push({ id: tempId, name: file.name, uploading: true });
      this.renderComposerState();
      try {
        const form = new FormData();
        form.append('file', file, file.name);
        form.append('conversation_id', String(this.state.activeConversationId || 0));
        const data = await this.api('upload', { method: 'POST', body: form });
        this.state.pendingAttachments = this.state.pendingAttachments.filter(a => a.id !== tempId);
        if (data.attachment) this.state.pendingAttachments.push(data.attachment);
      } catch (error) {
        this.state.pendingAttachments = this.state.pendingAttachments.filter(a => a.id !== tempId);
        this.toast(error.message, 'error');
      }
      this.renderComposerState();
    }

    renderComposerState() {
      const reply = this.root.querySelector('[data-role="reply-banner"]');
      if (reply) {
        if (this.state.replyTo) {
          reply.hidden = false;
          reply.innerHTML = `<div><strong>${esc(labels.reply || 'Reply')}</strong><span>${esc(this.state.replyTo.content || '')}</span></div><button type="button" data-action="cancel-reply">×</button>`;
        } else {
          reply.hidden = true;
          reply.innerHTML = '';
        }
      }
      const pending = this.root.querySelector('[data-role="pending-attachments"]');
      if (pending) {
        pending.innerHTML = this.state.pendingAttachments.map(a => `<div class="rcm-pending-file ${a.uploading ? 'is-uploading' : ''}"><span>${a.uploading ? '<i class="rcm-spinner"></i>' : '▧'}</span><strong>${esc(a.name || 'Attachment')}</strong>${!a.uploading ? `<button type="button" data-action="remove-pending-attachment" data-attachment-id="${Number(a.id)}">×</button>` : ''}</div>`).join('');
      }
    }

    removePendingAttachment(id) {
      this.state.pendingAttachments = this.state.pendingAttachments.filter(a => Number(a.id) !== Number(id));
      this.renderComposerState();
    }

    setReply(id) {
      const message = this.state.messages.find(m => Number(m.id) === Number(id));
      if (!message) return;
      this.state.replyTo = { id: message.id, content: message.content || labels.messageDeleted || 'Message deleted' };
      this.renderComposerState();
      this.root.querySelector('[data-role="composer"]')?.focus();
    }

    async editMessage(id) {
      const message = this.state.messages.find(m => Number(m.id) === Number(id));
      if (!message) return;
      const value = window.prompt(labels.edit || 'Edit message', message.content || '');
      if (value == null || !value.trim()) return;
      try {
        const data = await this.api(`messages/${id}`, { method: 'PUT', body: { content: value.trim() } });
        if (data.message) this.replaceMessage(data.message);
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async deleteMessage(id) {
      if (!window.confirm('Delete this message for everyone?')) return;
      try {
        await this.api(`messages/${id}`, { method: 'DELETE' });
        const message = this.state.messages.find(m => Number(m.id) === Number(id));
        if (message) { message.deleted = true; message.content = ''; message.attachments = []; }
        this.renderMessages(true);
      } catch (error) { this.toast(error.message, 'error'); }
    }

    toggleReactionMenu(id) {
      this.root.querySelectorAll('[data-reaction-menu]').forEach(menu => {
        if (Number(menu.dataset.reactionMenu) === Number(id)) menu.hidden = !menu.hidden;
        else menu.hidden = true;
      });
    }

    async react(id, reaction) {
      try {
        const data = await this.api(`messages/${id}/reaction`, { method: 'POST', body: { reaction } });
        if (data.message) this.replaceMessage(data.message);
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async reportMessage(id) {
      const reason = window.prompt('Why are you reporting this message?') || '';
      if (reason === '' && !window.confirm('Submit the report without a reason?')) return;
      try {
        await this.api(`messages/${id}/report`, { method: 'POST', body: { reason } });
        this.toast('Message reported.', 'success');
      } catch (error) { this.toast(error.message, 'error'); }
    }

    replaceMessage(message) {
      const index = this.state.messages.findIndex(m => Number(m.id) === Number(message.id));
      if (index >= 0) this.state.messages[index] = message;
      this.renderMessages(true);
    }

    async refreshConversations() {
      try {
        const previous = new Map(this.state.conversations.map(c => [Number(c.id), Number(c.last_message_id || 0)]));
        const data = await this.api('conversations');
        const incoming = data.conversations || [];
        const changedInactive = incoming.filter(c => {
          const oldId = previous.get(Number(c.id)) || 0;
          return Number(c.id) !== Number(this.state.activeConversationId) && Number(c.last_message_id || 0) > oldId && Number(c.last_message?.sender_id || 0) !== Number(this.state.user?.id) && Number(c.unread || 0) > 0;
        });
        this.state.conversations = incoming;
        this.renderSidebar();
        const active = this.activeConversation();
        if (active) this.renderTyping(active.typing || []);
        changedInactive.forEach(c => this.notifyConversationSummary(c));
        this.emitUnread();
      } catch (error) {}
    }

    startPolling() {
      const interval = Math.max(2000, Number(cfg.pollInterval || this.state.limits.poll_interval_ms || 3500));
      this.poller = window.setInterval(async () => {
        if (document.hidden) return;
        await this.refreshConversations();
        if (this.state.activeConversationId) await this.fetchMessages(false, false);
      }, interval);
    }

    heartbeatPresence() {
      this.api('presence', { method: 'POST', body: { status: this.state.user?.presence || 'online' } }).catch(() => {});
      this.presenceTimer = window.setInterval(() => {
        if (!document.hidden) this.api('presence', { method: 'POST', body: { status: this.state.user?.presence || 'online' } }).catch(() => {});
      }, 45000);
    }

    signalTyping() {
      if (!this.state.activeConversationId) return;
      const now = Date.now();
      if (now - this.lastTypingPing > 2500) {
        this.lastTypingPing = now;
        this.api(`conversations/${this.state.activeConversationId}/typing`, { method: 'POST', body: { typing: true } }).catch(() => {});
      }
      clearTimeout(this.typingStopTimer);
      this.typingStopTimer = setTimeout(() => this.stopTyping(), 3500);
    }

    stopTyping() {
      clearTimeout(this.typingStopTimer);
      if (!this.state.activeConversationId) return;
      this.api(`conversations/${this.state.activeConversationId}/typing`, { method: 'POST', body: { typing: false } }).catch(() => {});
    }

    activeConversation() {
      return this.state.conversations.find(c => Number(c.id) === Number(this.state.activeConversationId)) || null;
    }

    conversationSubtitle(c) {
      if (!c) return '';
      if (c.type === 'group') return `${(c.members || []).length} members`;
      const other = (c.members || []).find(m => Number(m.id) !== Number(this.state.user?.id));
      if (!other) return '';
      if (this.state.features.presence && other.presence === 'online') return labels.online || 'Online';
      if (this.state.features.last_seen && other.last_seen) return `Last seen ${fmtFullTime(other.last_seen + 'Z')}`;
      if (this.state.features.presence) return this.presenceLabel(other.presence);
      return '';
    }

    conversationPresenceDot(c) {
      if (!this.state.features.presence || c.type !== 'direct') return '';
      const other = (c.members || []).find(m => Number(m.id) !== Number(this.state.user?.id));
      return other?.presence === 'online' ? '<i class="rcm-online-dot"></i>' : '';
    }

    presenceLabel(presence) {
      return labels[presence] || presence || labels.online || 'Online';
    }

    userSecondary(user) {
      if (this.state.features.presence && user.presence === 'online') return labels.online || 'Online';
      return (user.roles || []).join(', ');
    }

    avatarHtml(user, size = 'md') {
      const name = user?.name || '';
      return user?.avatar
        ? `<span class="rcm-avatar rcm-avatar-${size}"><img src="${esc(user.avatar)}" alt="${esc(name)}" loading="lazy"></span>`
        : `<span class="rcm-avatar rcm-avatar-${size} rcm-avatar-fallback">${esc(initials(name))}</span>`;
    }

    formatBytes(bytes) {
      const n = Number(bytes || 0);
      if (!n) return '';
      const units = ['B','KB','MB','GB'];
      const i = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1);
      return `${(n / Math.pow(1024, i)).toFixed(i ? 1 : 0)} ${units[i]}`;
    }

    resizeComposer(el) {
      el.style.height = 'auto';
      el.style.height = Math.min(140, el.scrollHeight) + 'px';
    }

    async startDirect(userId) {
      try {
        const data = await this.api('conversations/direct', { method: 'POST', body: { user_id: userId } });
        this.closeModal();
        await this.refreshConversations();
        if (data.conversation_id) await this.openConversation(Number(data.conversation_id));
      } catch (error) { this.toast(error.message, 'error'); }
    }

    openUserPicker(groupMode) {
      const title = groupMode ? (labels.newGroup || 'New group') : (labels.newChat || 'New chat');
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      if (!layer) return;
      layer.innerHTML = `<div class="rcm-modal-backdrop" data-action="close-modal"></div><section class="rcm-modal" role="dialog" aria-modal="true">
        <header><div><h3>${esc(title)}</h3><p>${groupMode ? 'Choose participants permitted by the role matrix.' : 'Only users you are permitted to contact are listed.'}</p></div><button type="button" data-action="close-modal">×</button></header>
        ${groupMode ? `<label class="rcm-modal-field"><span>Group name</span><input type="text" data-role="group-title" maxlength="190" placeholder="Editorial Team"></label>` : ''}
        <div class="rcm-search-box rcm-modal-search"><span>⌕</span><input type="search" data-role="user-search" placeholder="Search users…" autofocus></div>
        <div class="rcm-user-picker-list" data-role="user-picker-list"><div class="rcm-message-loader"><span class="rcm-spinner"></span></div></div>
        ${groupMode ? `<footer><span data-role="group-selection-count">0 selected</span><button type="button" class="rcm-primary-btn" data-role="create-group-btn" disabled>Create group</button></footer>` : ''}
      </section>`;
      layer.classList.add('is-open');
      layer.dataset.groupMode = groupMode ? '1' : '0';
      this.loadUsersIntoModal('');
      if (groupMode) {
        const button = layer.querySelector('[data-role="create-group-btn"]');
        button.addEventListener('click', () => this.createGroupFromModal());
      }
    }

    async loadUsersIntoModal(search) {
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      const list = layer?.querySelector('[data-role="user-picker-list"]');
      if (!list) return;
      list.innerHTML = `<div class="rcm-message-loader"><span class="rcm-spinner"></span></div>`;
      try {
        const data = await this.api(`users?limit=40&search=${encodeURIComponent(search || '')}`);
        const users = data.users || [];
        const groupMode = layer.dataset.groupMode === '1';
        if (!users.length) { list.innerHTML = '<div class="rcm-mini-empty">No permitted users found.</div>'; return; }
        list.innerHTML = users.map(user => groupMode
          ? `<label class="rcm-user-pick-row"><input type="checkbox" data-role="group-member" value="${Number(user.id)}"><span>${this.avatarHtml(user, 'sm')}<span><strong>${esc(user.name)}</strong><small>${esc(this.userSecondary(user))}</small></span></span></label>`
          : `<button type="button" class="rcm-user-pick-row" data-contact-user="${Number(user.id)}">${this.avatarHtml(user, 'sm')}<span><strong>${esc(user.name)}</strong><small>${esc(this.userSecondary(user))}</small></span><i>›</i></button>`
        ).join('');
        if (groupMode) {
          list.querySelectorAll('[data-role="group-member"]').forEach(cb => cb.addEventListener('change', () => this.updateGroupSelection()));
        }
      } catch (error) { list.innerHTML = `<div class="rcm-mini-empty">${esc(error.message)}</div>`; }
    }

    updateGroupSelection() {
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      const selected = layer ? Array.from(layer.querySelectorAll('[data-role="group-member"]:checked')) : [];
      const count = layer?.querySelector('[data-role="group-selection-count"]');
      const button = layer?.querySelector('[data-role="create-group-btn"]');
      if (count) count.textContent = `${selected.length} selected`;
      if (button) button.disabled = selected.length < 1;
    }

    async createGroupFromModal() {
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      if (!layer) return;
      const title = layer.querySelector('[data-role="group-title"]')?.value.trim() || '';
      const members = Array.from(layer.querySelectorAll('[data-role="group-member"]:checked')).map(cb => Number(cb.value));
      if (!title || !members.length) { this.toast('Group name and at least one participant are required.', 'error'); return; }
      try {
        const data = await this.api('conversations/group', { method: 'POST', body: { title, member_ids: members } });
        this.closeModal();
        await this.refreshConversations();
        if (data.conversation_id) await this.openConversation(Number(data.conversation_id));
      } catch (error) { this.toast(error.message, 'error'); }
    }

    closeModal() {
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      if (layer) { layer.classList.remove('is-open'); layer.innerHTML = ''; delete layer.dataset.groupMode; }
    }

    async updateConversationState(payload) {
      const c = this.activeConversation();
      if (!c) return;
      try {
        await this.api(`conversations/${c.id}/state`, { method: 'PUT', body: payload });
        await this.refreshConversations();
        this.renderChatFrame();
        await this.fetchMessages(false, true);
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async toggleMute() {
      const c = this.activeConversation();
      if (!c) return;
      const muted = c.muted_until && new Date(c.muted_until + 'Z').getTime() > Date.now();
      if (muted) return this.updateConversationState({ muted_minutes: 0 });
      const choice = window.prompt('Mute for how many minutes? Examples: 60, 480, 1440, 10080', '480');
      if (choice == null) return;
      const minutes = Math.max(0, Number.parseInt(choice, 10) || 0);
      return this.updateConversationState({ muted_minutes: minutes });
    }

    toggleDetails() {
      const pane = this.root.querySelector('[data-role="details-pane"]');
      if (!pane) return;
      if (!pane.hidden) { pane.hidden = true; this.root.classList.remove('rcm-details-open'); return; }
      const c = this.activeConversation();
      if (!c) return;
      pane.hidden = false;
      this.root.classList.add('rcm-details-open');
      pane.innerHTML = this.detailsHtml(c);
    }

    detailsHtml(c) {
      const members = c.members || [];
      if (c.type === 'group') {
        return `<div class="rcm-details-head"><button type="button" class="rcm-icon-btn" data-action="details">×</button><span>${this.avatarHtml({ name: c.title, avatar: c.avatar }, 'lg')}</span><h3>${esc(c.title)}</h3><p>${members.length} members</p></div>
          <div class="rcm-details-section"><div class="rcm-details-section-title"><strong>Members</strong>${c.can_manage ? `<button type="button" data-action="add-group-member">＋ ${esc(labels.addMember || 'Add member')}</button>` : ''}</div>${members.map(member => `<div class="rcm-detail-member">${this.avatarHtml(member, 'sm')}<span><strong>${esc(member.name)}</strong><small>${esc(this.userSecondary(member))}</small></span>${c.can_manage && Number(member.id) !== Number(this.state.user.id) && Number(member.id) !== Number(c.created_by) ? `<button type="button" data-action="remove-group-member" data-user-id="${Number(member.id)}">×</button>` : ''}</div>`).join('')}</div>
          <div class="rcm-details-section"><button type="button" class="rcm-danger-row" data-action="leave-group">↪ ${esc(labels.leaveGroup || 'Leave group')}</button><button type="button" data-action="archive">▣ ${esc(c.is_archived ? labels.unarchive : labels.archive)}</button></div>`;
      }
      const other = members.find(m => Number(m.id) !== Number(this.state.user.id));
      if (!other) return '';
      const contact = this.state.contacts.some(ct => Number(ct.id) === Number(other.id));
      const blocked = this.state.blockedUsers.includes(Number(other.id));
      return `<div class="rcm-details-head"><button type="button" class="rcm-icon-btn" data-action="details">×</button><span>${this.avatarHtml(other, 'lg')}</span><h3>${esc(other.name)}</h3><p>${esc(this.conversationSubtitle(c))}</p></div>
        <div class="rcm-details-section">
          <button type="button" data-action="${contact ? 'remove-contact' : 'add-contact'}">${contact ? '−' : '+'} ${esc(contact ? labels.removeContact : labels.addContact)}</button>
          <button type="button" data-action="archive">▣ ${esc(c.is_archived ? labels.unarchive : labels.archive)}</button>
          ${this.state.features.blocking ? `<button type="button" class="${blocked ? '' : 'rcm-danger-row'}" data-action="${blocked ? 'unblock-user' : 'block-user'}">${blocked ? '✓ Unblock user' : `⊘ ${esc(labels.block || 'Block user')}`}</button>` : ''}
        </div>`;
    }

    async addActiveContact() {
      const other = this.activeOtherUser();
      if (!other) return;
      const category = this.state.categories.length ? window.prompt(`Category ID (optional): ${this.state.categories.map(c => `${c.id}=${c.name}`).join(', ')}`, '0') : '0';
      try {
        const data = await this.api('contacts', { method: 'POST', body: { user_id: other.id, category_id: Number(category || 0) } });
        this.state.contacts = data.contacts || [];
        this.toggleDetails(); this.toggleDetails(); this.renderSidebar();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async removeActiveContact() {
      const other = this.activeOtherUser();
      if (!other) return;
      try {
        await this.api(`contacts/${other.id}`, { method: 'DELETE' });
        this.state.contacts = this.state.contacts.filter(c => Number(c.id) !== Number(other.id));
        this.toggleDetails(); this.toggleDetails(); this.renderSidebar();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    activeOtherUser() {
      const c = this.activeConversation();
      return c?.type === 'direct' ? (c.members || []).find(m => Number(m.id) !== Number(this.state.user.id)) : null;
    }

    async blockActiveUser() {
      const other = this.activeOtherUser();
      if (!other || !window.confirm(`Block ${other.name}? Messaging between you will be prevented.`)) return;
      try {
        await this.api(`blocks/${other.id}`, { method: 'POST', body: {} });
        if (!this.state.blockedUsers.includes(Number(other.id))) this.state.blockedUsers.push(Number(other.id));
        this.toggleDetails(); this.toggleDetails();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async unblockActiveUser() {
      const other = this.activeOtherUser();
      if (!other) return;
      try {
        await this.api(`blocks/${other.id}`, { method: 'DELETE' });
        this.state.blockedUsers = this.state.blockedUsers.filter(id => Number(id) !== Number(other.id));
        this.toggleDetails(); this.toggleDetails();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async createCategoryPrompt() {
      const name = window.prompt('Contact category name');
      if (!name?.trim()) return;
      try {
        const data = await this.api('contact-categories', { method: 'POST', body: { name: name.trim() } });
        this.state.categories = data.categories || [];
        this.renderSidebar();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async renameCategory(id) {
      const category = this.state.categories.find(c => Number(c.id) === Number(id));
      if (!category) return;
      const name = window.prompt('Rename contact category', category.name || '');
      if (!name?.trim()) return;
      try {
        const data = await this.api(`contact-categories/${id}`, { method: 'PUT', body: { name: name.trim() } });
        this.state.categories = data.categories || [];
        this.renderSidebar();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async deleteCategory(id) {
      if (!window.confirm('Delete this category? Contacts will be kept uncategorized.')) return;
      try {
        await this.api(`contact-categories/${id}`, { method: 'DELETE' });
        this.state.categories = this.state.categories.filter(c => Number(c.id) !== Number(id));
        this.state.contacts.forEach(c => { if (Number(c.category_id) === Number(id)) c.category_id = 0; });
        this.renderSidebar();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    openForwardPicker(messageId) {
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      if (!layer) return;
      const choices = this.state.conversations.filter(c => !c.is_archived && Number(c.id) !== Number(this.state.activeConversationId));
      layer.innerHTML = `<div class="rcm-modal-backdrop" data-action="close-modal"></div><section class="rcm-modal rcm-modal-small"><header><div><h3>Forward message</h3><p>Select a destination conversation.</p></div><button type="button" data-action="close-modal">×</button></header><div class="rcm-user-picker-list">${choices.length ? choices.map(c => `<button type="button" class="rcm-user-pick-row" data-forward-to="${Number(c.id)}">${this.avatarHtml({name:c.title,avatar:c.avatar}, 'sm')}<span><strong>${esc(c.title)}</strong><small>${esc(c.type)}</small></span><i>›</i></button>`).join('') : '<div class="rcm-mini-empty">No other conversations available.</div>'}</div></section>`;
      layer.classList.add('is-open');
      layer.querySelectorAll('[data-forward-to]').forEach(btn => btn.addEventListener('click', async () => {
        try {
          await this.api(`messages/${messageId}/forward`, { method: 'POST', body: { conversation_id: Number(btn.dataset.forwardTo) } });
          this.closeModal(); this.toast('Message forwarded.', 'success'); await this.refreshConversations();
        } catch (error) { this.toast(error.message, 'error'); }
      }));
    }

    openMessageSearch() {
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      if (!layer || !this.activeConversation()) return;
      layer.innerHTML = `<div class="rcm-modal-backdrop" data-action="close-modal"></div><section class="rcm-modal"><header><div><h3>Search messages</h3><p>${esc(this.activeConversation().title)}</p></div><button type="button" data-action="close-modal">×</button></header><div class="rcm-search-box rcm-modal-search"><span>⌕</span><input type="search" data-role="message-search" placeholder="Type at least 2 characters…" autofocus></div><div class="rcm-message-search-results" data-role="message-search-results"><div class="rcm-mini-empty">Enter a search term.</div></div></section>`;
      layer.classList.add('is-open');
    }

    async performMessageSearch(q) {
      const host = this.root.querySelector('[data-role="message-search-results"]');
      if (!host) return;
      if (q.trim().length < 2) { host.innerHTML = '<div class="rcm-mini-empty">Enter at least 2 characters.</div>'; return; }
      host.innerHTML = `<div class="rcm-message-loader"><span class="rcm-spinner"></span></div>`;
      try {
        const data = await this.api(`conversations/${this.state.activeConversationId}/search?q=${encodeURIComponent(q.trim())}`);
        const items = data.messages || [];
        host.innerHTML = items.length ? items.map(m => `<button type="button" class="rcm-search-result" data-search-message-id="${Number(m.id)}"><strong>${esc(m.sender?.name || 'User')}</strong><span>${esc(m.content || labels.messageDeleted || '')}</span><time>${esc(fmtFullTime(m.created_at))}</time></button>`).join('') : '<div class="rcm-mini-empty">No messages found.</div>';
        host.querySelectorAll('[data-search-message-id]').forEach(btn => btn.addEventListener('click', () => {
          const id = Number(btn.dataset.searchMessageId);
          const exists = this.state.messages.some(m => Number(m.id) === id);
          if (exists) {
            this.closeModal();
            this.root.querySelector(`[data-message-id="${id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          } else this.toast('Load earlier history to view this result.', 'info');
        }));
      } catch (error) { host.innerHTML = `<div class="rcm-mini-empty">${esc(error.message)}</div>`; }
    }

    openAddGroupMember() {
      const c = this.activeConversation();
      if (!c?.can_manage) return;
      this.openUserPicker(false);
      const layer = this.root.querySelector('[data-role="modal-layer"]');
      layer.dataset.addToGroup = String(c.id);
      layer.querySelector('h3').textContent = labels.addMember || 'Add member';
    }

    async removeGroupMember(userId) {
      const c = this.activeConversation();
      if (!c || !window.confirm('Remove this member from the group?')) return;
      try {
        await this.api(`conversations/${c.id}/members/${userId}`, { method: 'DELETE' });
        await this.refreshConversations(); this.toggleDetails(); this.toggleDetails();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async leaveGroup() {
      const c = this.activeConversation();
      if (!c || !window.confirm('Leave this group?')) return;
      try {
        await this.api(`conversations/${c.id}/members/${this.state.user.id}`, { method: 'DELETE' });
        this.state.activeConversationId = 0; await this.refreshConversations(); this.root.classList.remove('rcm-has-active-chat'); this.renderEmpty();
      } catch (error) { this.toast(error.message, 'error'); }
    }

    async requestNotifications() {
      if (!('Notification' in window)) return;
      const permission = await Notification.requestPermission();
      this.toast(permission === 'granted' ? 'Browser notifications enabled.' : 'Browser notifications were not enabled.', permission === 'granted' ? 'success' : 'info');
    }

    notifyConversationSummary(c) {
      if (!c) return;
      const muted = c.muted_until && new Date(c.muted_until + 'Z').getTime() > Date.now();
      if (muted) return;
      const sender = (c.members || []).find(m => Number(m.id) === Number(c.last_message?.sender_id));
      const content = c.last_message?.content || 'Attachment';
      const username = String(this.state.user?.username || '').toLowerCase();
      const isMention = this.state.features.mentions && username && new RegExp(`(^|\\s)@${username.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i').test(content);
      if (this.state.features.browser_notifications && 'Notification' in window && Notification.permission === 'granted') {
        try { new Notification(isMention ? `Mention in ${c.title}` : c.title, { body: content, icon: sender?.avatar || c.avatar || undefined }); } catch (e) {}
      }
      if (this.state.features.sounds) this.playPing();
    }

    notifyNewMessages(messages) {
      if (!messages.length) return;
      const c = this.activeConversation();
      const muted = c?.muted_until && new Date(c.muted_until + 'Z').getTime() > Date.now();
      if (!muted && this.state.features.browser_notifications && 'Notification' in window && Notification.permission === 'granted') {
        const latest = messages[messages.length - 1];
        const mentioned = (latest.mention_user_ids || []).map(Number).includes(Number(this.state.user?.id));
        try { new Notification(mentioned ? `Mention in ${c?.title || 'group'}` : (c?.title || labels.newMessage || 'New message'), { body: latest.content || 'Attachment', icon: latest.sender?.avatar || undefined }); } catch (e) {}
      }
      if (!muted && this.state.features.sounds) this.playPing();
    }

    playPing() {
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = 740; gain.gain.value = 0.025;
        osc.connect(gain); gain.connect(ctx.destination); osc.start(); gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.12); osc.stop(ctx.currentTime + 0.12);
      } catch (e) {}
    }

    emitUnread() {
      const unread = this.state.conversations.reduce((sum, c) => sum + Number(c.unread || 0), 0);
      this.root.dispatchEvent(new CustomEvent('rcm:unread', { bubbles: true, detail: { unread } }));
    }

    toast(message, type = 'info') {
      const stack = this.root.querySelector('[data-role="toasts"]');
      if (!stack) return;
      const item = document.createElement('div');
      item.className = `rcm-toast is-${type}`;
      item.textContent = message;
      stack.appendChild(item);
      setTimeout(() => item.classList.add('is-visible'), 20);
      setTimeout(() => { item.classList.remove('is-visible'); setTimeout(() => item.remove(), 250); }, 3500);
    }

    renderFatal(message) {
      this.root.innerHTML = `<div class="rcm-fatal"><strong>RoleChat could not start.</strong><p>${esc(message)}</p></div>`;
    }
  }

  window.RoleChatApp = RoleChatApp;
})();
