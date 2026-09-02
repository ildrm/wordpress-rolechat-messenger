# Changelog

## 1.0.1

- Enforce reply and attachment permissions independently for mixed messages and forwards.
- Bind pending encrypted uploads to the conversation that authorized them and rate-limit upload/report/group-create abuse paths.
- Authenticate complete encrypted files before sending any plaintext and reject trailing ciphertext data or partial writes.
- Fix historical-message loading, reply jumps, duplicate sends, stale chat/search responses, modal state leakage, and group-owner transfer state.
- Stop hidden frontend widgets from marking messages as read while preserving unread polling and notifications.
- Restrict private conversation inspection to administrators and gate audit-log output behind its dedicated capability.
- Make retention cleanup transactional, prevent overlapping cleanup workers, include report cleanup, and schedule continuation batches for large backlogs.
- Handle network-wide multisite activation/deactivation and remove site data, RoleChat user metadata, transients, and cleanup hooks according to uninstall retention settings.
- Reduce conversation polling queries by batching memberships and unread/delivery work.
- Restore touch access to frontend message actions and improve keyboard focus, RTL layout, and CSS fallback behavior.
- Add a committed regression harness and GitHub Actions checks for PHP 8.1 and 8.4.

## 1.0.0

- Initial release.
- Directional WordPress role permission matrix.
- wp-admin Telegram-inspired messenger.
- Frontend floating messenger for configured roles.
- Direct and group chat.
- Pairwise group authorization checks.
- Contacts and contact categories.
- Encrypted attachments with MIME/size/role validation and authenticated download streaming.
- Replies, reactions, edit/delete, forwarding and reporting.
- Pin/archive/mute state.
- Typing, selectable presence, stale-session offline detection, last-seen, unread/read/delivery state.
- Group @username mention detection and mention-aware notifications.
- Browser notification support and message sound.
- Moderation queue, user suspensions and audit log.
- Retention cleanup and orphan attachment cleanup.
- Responsive and RTL-friendly logical layout primitives.
