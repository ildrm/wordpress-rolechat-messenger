# RoleChat Messenger QA Notes

The repository includes an executable regression harness at `tests/run.php` and a GitHub Actions workflow at `.github/workflows/ci.yml`. Run the local checks below before packaging; CI repeats syntax and regression checks on PHP 8.1 and 8.4.

## Local commands

```bash
php tests/run.php
php -l rolechat-messenger.php
php -l uninstall.php
php -l includes/*.php
node --check assets/js/chat-core.js
node --check assets/js/frontend.js
node --check assets/js/admin.js
```

The wildcard PHP command is suitable for shells that expand globs. On Windows PowerShell, invoke `php -l` once per file.

## Pass 1 — Syntax and bootstrap integrity

- PHP syntax is checked across every plugin PHP file (the current local audit used PHP 8.2; CI covers PHP 8.1 and 8.4).
- JavaScript syntax checked for all bundled JS files with Node.js.
- The committed harness loads the permission and encrypted-attachment classes with focused WordPress stubs.
- Full plugin bootstrap and activation/deactivation still require the staging pass described below.

## Pass 2 — Authorization and security review

Reviewed server-side authorization paths for:

- authenticated chat access;
- directional initiate/reply/attachment permissions;
- conversation membership;
- group compatibility;
- blocking;
- user suspension;
- editing/deleting/reacting/typing;
- moderation actions;
- settings nonces;
- attachment download authorization;
- private conversation inspection.

Issues found during this pass were corrected, including stale presence reporting, privacy-disabled presence leakage, post-membership message editing, and nonce protection for privacy-sensitive inspection links.

## Pass 3 — Executable permission tests

Earlier release notes recorded a broader 19-case private harness. That harness was not present in the repository and was therefore not reproducible. The new committed harness covers:

- directional role rules;
- reply permissions independent from initiation;
- attachment permissions independent from text messaging;
- text/attachment permission separation, including mixed messages;
- conversation membership and active-conversation checks through the permission core;
- independent message and upload rate-limit buckets.

It specifically prevents mixed text/file messages from bypassing either independent permission and verifies separate message/upload rate buckets.

## Pass 4 — Secure attachment and helper tests

The committed suite tests encrypted attachment storage with PHP Sodium using:

- empty files;
- small binary files;
- exact 1 MiB files;
- multi-chunk files larger than 2 MiB;
- deliberate ciphertext tampering;
- persistent master-secret reuse;
- upload-directory failure handling;
- rejection of trailing ciphertext without exposing partially authenticated plaintext.

Round-trip bytes must match and tampering must be rejected before output.

Earlier QA notes also recorded the following checks, but the original harness was not committed; treat them as staging checklist items rather than reproducible automated results:

- stale presence becomes offline;
- disabled presence/last-seen data is removed from REST-oriented user payloads;
- group mentions resolve only to conversation members;
- UTC message timestamps serialize correctly.

## Pass 5 — Structural and release integrity

The committed structural checks verify:

- every referenced RoleChat custom table exists in the installer schema;
- all 10 expected custom tables are defined;
- 23 REST route registrations define 29 protected methods;
- every REST method has a permission callback;
- every API family used by the JavaScript client exists server-side;
- message content and attachment URLs are escaped before client-side HTML insertion;
- CSS braces are balanced;
- all include files are loaded by the plugin entry point;
- plugin/stable versions remain synchronized;
- the client retains handlers for lazy history and reply jumps;
- pending attachments remain bound to their authorized conversation.

Release packaging should additionally re-open the final ZIP and verify its file list and hash; this repository does not currently include a packaging script.

## Environment limitation

The local audit environment does not provide a running MySQL/MariaDB-backed WordPress installation or browser automation stack. Therefore these checks do not replace staging tests on the target WordPress hosting environment. Before production deployment, test activation and the 1.1.0 schema migration, InnoDB transaction behavior, role combinations, REST requests, uploads/downloads, cron continuations, mobile/RTL/keyboard layouts, simultaneous multi-user messaging, and (when applicable) network activation/deactivation/uninstall on a multisite staging clone of the target site.
