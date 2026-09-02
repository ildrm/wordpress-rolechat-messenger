=== RoleChat Messenger ===
Contributors: ildrm
Tags: wordpress chat, internal messaging, frontend chat, role permissions, group chat, support chat, telegram style, messaging
Requires at least: 6.5
Requires PHP: 8.1
Stable tag: 1.0.1
License: MIT

Secure Telegram-inspired WordPress messaging with directional role permissions, direct/group chat, contacts, moderation and a frontend widget.

== Description ==

RoleChat Messenger provides one secure messaging platform for WordPress staff and frontend users.

Administrators define a directional role-to-role permission matrix. Backend roles can use a full wp-admin messenger, while configured roles such as subscribers can use a compact floating frontend widget.

Features include direct and group conversations, contacts/categories, encrypted attachments, replies, reactions, editing/deletion, forwarding, search, typing, presence, mentions, unread/read state, browser notifications, blocking, moderation, user chat suspension, audit logs and message retention.

WordPress multisite network activation is supported; configuration and chat data remain site-scoped.

== Installation ==

1. Upload and activate the plugin.
2. Open Chat > Settings.
3. Enable roles and choose frontend-widget roles.
4. Configure the directional role permission matrix.
5. Review security, upload, privacy and retention settings.

== Security ==

Private REST endpoints require authenticated WordPress access and permission callbacks. Role and conversation authorization is checked on the server. Attachment uploads use WordPress MIME validation plus RoleChat size, extension, rate and conversation-binding rules. Accepted chat files are encrypted at rest with PHP Sodium, fully authenticated, and only then streamed after a membership check.

== Changelog ==

= 1.0.1 =
* Security, authorization, encrypted-file integrity, cleanup, moderation privacy, client reliability, accessibility, performance and automated-QA fixes.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
This update adds an attachment conversation-binding column automatically and includes security and data-integrity fixes. Back up the database and test the upgrade on staging before production deployment.
