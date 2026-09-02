# RoleChat Messenger Audit

Audit date: 2026-09-02  
Audited release: 1.0.1 / database schema 1.1.0

## Scope

The audit covered every committed PHP, JavaScript, CSS, metadata, lifecycle, and documentation file. It reviewed authorization, privacy, input handling, encrypted attachments, data integrity, REST behavior, client state, accessibility, RTL behavior, performance, activation/deactivation/uninstall, retention, and the release/test process.

## Findings corrected

### Authorization, security, and data integrity

- Mixed text/file messages and forwards could use attachment permission to bypass denied reply permission. Text and attachment authorization are now evaluated independently.
- Pending uploads were bound only to an uploader and could be attached to another conversation. They are now bound and atomically claimed by uploader and conversation.
- Decryption emitted plaintext before the complete encrypted stream was authenticated. Downloads now authenticate into a temporary stream before emitting headers or plaintext.
- Encrypted streams accepted trailing data and file writes did not handle partial `fwrite()` results. Both cases now fail closed.
- Invalid storage keys and WordPress upload-directory errors could produce unsafe paths. Storage paths now require an exact random-key format and a valid upload base directory.
- Upload, report, and group-creation endpoints lacked independent abuse limits. Separate rate-limit buckets and duplicate open-report suppression were added.
- Message, group-title, report-reason, category-name, filename, group-size, and mute-duration inputs were insufficiently bounded. Server-side limits now match or exceed UI constraints safely.
- User search included email addresses, allowing email-based account discovery. Search is now limited to public account-name fields.
- Deleted or self-authored messages could be reported, and deleted messages could receive reactions. These operations are now rejected.
- Several database mutations returned success after failed writes. Message, membership, contact, category, block, reaction, report, and moderation paths now detect failure and compensate or fail closed.
- Concurrent direct/group membership operations could report success without a valid member row. Membership is verified and group-member insert races return an idempotent result.
- Group-owner transfer changed only the membership role, leaving `conversations.created_by` stale. Both records now change transactionally.
- Deleting a message purged attachment metadata without checking the delete result. Cleanup failure is now surfaced and moderation does not falsely resolve the report.
- Retention removed messages without their reports and could leave inconsistent conversation pointers after partial failures. Related rows and pointers are now updated transactionally before storage deletion.

### Product and client behavior

- The “Load earlier” control had no delegated action handler, and reply previews had no jump handler. Both interactions now work.
- Rapid conversation changes allowed stale message responses to replace the active chat. Per-resource request tokens now discard stale responses.
- A global loading flag could prevent a newly selected conversation from loading. Loading state is now conversation-specific.
- User and message searches could render stale results after a newer query or modal closure. Search responses are now token- and connection-checked.
- Pressing Enter while a send was in flight could create duplicate messages. Sending is guarded independently of button state.
- A message could be sent while its attachment was still uploading. The composer now waits for every pending upload.
- Upload and send responses could attach to or render inside a different conversation after navigation. Operations capture their originating conversation and update only that state.
- Polling and a send response could duplicate the same message. Client message merging now de-duplicates by message ID.
- Modal group-add state leaked into later new-chat dialogs. Modal state and outstanding searches are reset on open/close.
- Existing group members remained selectable, and group size was not enforced consistently. Existing members are filtered and the server enforces a 200-member ceiling.
- Hidden frontend widgets marked conversations read and suppressed useful unread polling/notifications. Read receipts now require actual widget/page visibility while summary polling continues.
- Poll requests could overlap, and removed memberships left a stale active chat. Polling is serialized and invalid conversations are closed locally.
- Conversation timestamps were serialized as ambiguous database strings and interpreted in the browser’s local timezone. Server timestamps are now explicit UTC ISO-8601 values.
- Browser notification permission errors were unhandled. They now produce a controlled client error.

### Privacy, accessibility, and performance

- The dedicated audit-log capability was installed but ignored. Audit rows and IP addresses now require `rcm_view_audit_log` or administrator access.
- General moderators could inspect arbitrary private conversations when the administrator setting was enabled. Full inspection now additionally requires `manage_options` and a nonce.
- Frontend message actions were hover-dependent and effectively unavailable on touch devices. They are now persistently accessible in the compact widget.
- Hidden settings controls and chat actions lacked reliable keyboard focus indication, and the composer removed its outline. Focus-visible styling was restored.
- Several physical left/right CSS properties broke RTL layouts. Logical properties and RTL switch movement were added.
- A `color-mix()`-only declaration lacked a fallback. A compatible fallback now precedes it.
- Conversation polling performed repeated membership, unread, delivery, and user queries per conversation. Delivery/unread/member work is now batched and user payloads are cached per request.
- Frontend and admin clients carried divergent localization label sets. Both now use one server-generated label catalog, with additional labels for repaired UI paths.

### Lifecycle, cleanup, documentation, and process

- Cleanup handled only one daily batch, so large backlogs could remain indefinitely. Bounded continuation jobs now drain remaining work.
- Daily and continuation jobs could overlap. A stale-safe shared cleanup lock now serializes workers.
- Deactivation unscheduled only one occurrence and ignored continuation jobs. Both hooks are fully cleared.
- Full uninstall left RoleChat user metadata, transients, and operational locks. These are removed according to the configured retention choice.
- Network-wide multisite activation, deactivation, and uninstall affected only the current site. Lifecycle work now runs per site, with safe handling of network-global user metadata.
- A missing or failed attachment master-secret generation path was not safely handled. Generation is race-safe and upload remains unavailable rather than using an invalid key.
- Documentation described a private test harness that was not committed, making the claimed QA irreproducible. The claims were corrected and an executable repository test harness was added.
- The project had no automated CI gate. GitHub Actions now lints PHP, checks all JavaScript bundles, and runs regression coverage on PHP 8.1 and 8.4.
- Release/security/upgrade documentation did not describe the schema, privacy, attachment-authentication, rate-limit, retention, transaction, multisite, and staging implications. The relevant project documents now do.

## Verification

- Every PHP file passes `php -l` on PHP 8.2.12.
- All three JavaScript bundles pass `node --check`.
- The committed harness passes 71 permission, encrypted-file, lifecycle, schema, API, escaping, version, and structural checks.
- `git diff --check` passes.

The audit environment did not include a live WordPress/MySQL installation or browser automation. The remaining target-host integration matrix is documented in `TESTING.md` and must be run on staging before production deployment.
