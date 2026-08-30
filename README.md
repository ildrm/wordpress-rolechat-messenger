# RoleChat Messenger for WordPress

RoleChat Messenger is a secure, WordPress-native messaging plugin with a Telegram-inspired interface, directional role-to-role communication rules, direct and group conversations, personal contacts, a frontend support-style widget, attachments, moderation, presence, reactions, search, audit logging, and retention controls.

## Product goals

- Give administrators precise control over **who may contact whom**.
- Provide a polished messenger inside **wp-admin** for staff roles.
- Provide a compact floating **frontend chat widget** for roles such as subscribers/customers.
- Use one shared messaging and authorization core for both interfaces.
- Prevent frontend/UI manipulation from bypassing server-side role permissions.
- Keep message queries scalable by using dedicated database tables instead of posts/postmeta.

## Disciplines used to design and implement the plugin

The project was treated as a cross-functional product rather than only a PHP coding task. The implementation was reviewed through these roles:

1. Product owner / requirements analyst
2. WordPress plugin architect
3. Senior PHP / WordPress backend engineer
4. Database architect
5. REST API engineer
6. Messaging / near-real-time systems engineer
7. Frontend architect
8. UI designer
9. UX / interaction designer
10. Responsive/mobile frontend engineer
11. Accessibility reviewer
12. Application security engineer
13. Privacy / authorization reviewer
14. Performance engineer
15. Localization / RTL engineer
16. Moderation / support-workflow designer
17. QA / test engineer
18. Release / maintainability reviewer

## Main features

### Directional role permission matrix

Permissions are directional and evaluated independently:

- Administrator → Author can be enabled while Author → Administrator is disabled.
- Subscriber → Support can be enabled while Subscriber → Editor remains disabled.
- Custom WordPress roles are discovered dynamically.
- Three controls are available for every role pair:
  - Initiate conversation
  - Reply/send messages
  - Send attachments

Server-side authorization is checked for every sensitive operation.

### wp-admin messenger

- Top-level **Chat** menu for backend-chat roles
- Telegram-inspired three-pane desktop layout
- Responsive one-pane mobile layout
- Conversation list with avatars, previews, timestamps and unread badges
- Pinned, archived and muted conversations
- Direct messaging
- Group messaging
- Group member management
- Group owner transfer when the owner leaves
- Search conversations
- Search messages in the active conversation
- Lazy historical message loading
- Read and delivery state
- Typing indicators
- Presence and last-seen state with stale-session offline detection
- User-selectable availability: online, away, busy, do-not-disturb, offline
- Message replies
- Message editing
- Delete-for-everyone window
- Reactions
- Message forwarding
- Message reporting
- Encrypted image/file attachments with authenticated downloads
- Browser notifications for active and inactive conversations
- Group @username mention detection and mention notifications
- Message sound

### Contacts

- Personal contact list
- Personal contact categories
- Create, rename and delete categories
- Assign contacts to categories
- Search contacts
- Add/remove contacts

### Group authorization

Groups cannot be used to bypass the role matrix. Before a group is created or a new member is added, RoleChat verifies that every message direction required by the group is permitted by the configured role rules.

### Frontend chat widget

For configured frontend roles such as subscribers:

- Floating chat launcher
- Compact responsive messenger
- Existing conversation history
- Allowed-user discovery only
- Direct messaging
- Groups when permitted
- Attachments
- Replies/reactions
- Typing/presence
- Unread badge
- Browser notifications for active and inactive conversations
- Group @username mention detection and mention notifications
- Left/right position
- Configurable accent color, title and greeting
- Page include/exclude rules

The frontend and wp-admin applications use the same REST API, database and authorization engine.

### Moderation

- Report a message
- Moderator report queue
- Resolve reports
- Delete reported messages
- Suspend a specific user's chat access
- Timed suspension or permanent suspension
- Restore chat access
- Audit log
- Optional administrator conversation inspection
- Conversation inspection is explicitly configurable because it is privacy-sensitive

### Security hardening

- Authenticated WordPress REST requests
- REST `permission_callback` on private endpoints
- WordPress REST nonce
- Capability checks for administration/moderation
- Server-side role matrix authorization
- Conversation membership authorization
- Group matrix compatibility enforcement
- Block-list enforcement in both directions
- Message rate limiting
- Sanitization of user input
- Escaped output
- Restricted attachment extensions
- WordPress MIME validation
- PHP Sodium authenticated encryption for chat files
- Authenticated, nonce-protected attachment streaming
- Configurable upload size limit
- Attachment-to-conversation authorization before upload
- Orphan upload cleanup
- No trust in hidden/disabled frontend controls
- No public message endpoints
- User-specific chat suspension
- Audit log for security-sensitive actions

### Message lifecycle

- Configurable retention period
- Daily cleanup task
- Chat-owned media cleanup when retained messages are removed
- Orphan chat attachment cleanup after 24 hours
- Optional complete data removal on uninstall

## Database model

RoleChat creates dedicated WordPress-prefix-aware tables:

- `*_rcm_conversations`
- `*_rcm_members`
- `*_rcm_messages`
- `*_rcm_attachments`
- `*_rcm_reactions`
- `*_rcm_contacts`
- `*_rcm_contact_categories`
- `*_rcm_blocks`
- `*_rcm_reports`
- `*_rcm_audit_log`

Direct conversations use a deterministic unique key to reduce duplicate-conversation races.

## Near-real-time behavior

The bundled version uses authenticated REST polling by default for broad hosting compatibility. Polling frequency is configurable with a safe minimum. The architecture keeps the messaging API separate from the UI so a WebSocket/SSE transport can be added later without replacing the conversation model.

## Installation

1. Upload `rolechat-messenger.zip` from **Plugins → Add Plugin → Upload Plugin**.
2. Activate **RoleChat Messenger**.
3. Open **Chat → Settings**.
4. Select chat-enabled roles.
5. Select which roles should use the frontend widget.
6. Configure the directional role matrix.
7. Review security, retention, attachment and moderation settings.
8. Open **Chat** to start using the messenger.

## Recommended deployment configuration

- Serve WordPress over HTTPS.
- Keep WordPress and PHP patched.
- Use PHP 8.1 or newer.
- Set conservative attachment types/sizes for public-facing sites.
- Configure the role matrix using least privilege.
- Avoid enabling administrator conversation inspection unless there is a defined moderation/compliance need.
- Use object caching on high-volume WordPress installations.
- For very high chat throughput, replace polling with a dedicated WebSocket/SSE transport and perform load testing for the specific hosting stack.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- PHP Sodium extension for attachment uploads/downloads
- Logged-in WordPress users

## Notes

RoleChat is designed for authenticated WordPress users. It does not create an anonymous guest-chat identity system. Subscribers/customers can use the frontend widget when their role is enabled.

## License

MIT
