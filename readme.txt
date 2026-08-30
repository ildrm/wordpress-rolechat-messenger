=== RoleChat Messenger ===
Contributors: ildrm
Tags: wordpress chat, internal messaging, frontend chat, role permissions, group chat, support chat, telegram style, messaging
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure Telegram-inspired WordPress messaging with directional role permissions, direct/group chat, contacts, moderation and a frontend widget.

== Description ==

RoleChat Messenger provides one secure messaging platform for WordPress staff and frontend users.

Administrators define a directional role-to-role permission matrix. Backend roles can use a full wp-admin messenger, while configured roles such as subscribers can use a compact floating frontend widget.

Features include direct and group conversations, contacts/categories, encrypted attachments, replies, reactions, editing/deletion, forwarding, search, typing, presence, mentions, unread/read state, browser notifications, blocking, moderation, user chat suspension, audit logs and message retention.

== Installation ==

1. Upload and activate the plugin.
2. Open Chat > Settings.
3. Enable roles and choose frontend-widget roles.
4. Configure the directional role permission matrix.
5. Review security, upload, privacy and retention settings.

== Security ==

Private REST endpoints require authenticated WordPress access and permission callbacks. Role and conversation authorization is checked on the server. Attachment uploads use WordPress MIME validation plus RoleChat size, extension and conversation authorization rules. Accepted chat files are encrypted at rest with PHP Sodium and streamed only after authenticated membership checks.

== Changelog ==

= 1.0.0 =
* Initial release.
