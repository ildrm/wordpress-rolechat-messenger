# RoleChat Messenger QA Notes

This release was subjected to five separate QA passes before packaging.

## Pass 1 — Syntax and bootstrap integrity

- PHP syntax checked across every plugin PHP file with PHP 8.4.
- JavaScript syntax checked for all bundled JS files with Node.js.
- Plugin bootstrap loaded in a WordPress-function stub harness.
- Activation/deactivation and core action-hook registrations verified.

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

A standalone PHP harness executed 19 permission/rate-limit cases covering:

- directional role rules;
- reply permissions independent from initiation;
- attachment permissions independent from text messaging;
- backend/frontend role routing;
- group-creator rules;
- group send authorization across all members;
- group owner management;
- two-way block enforcement;
- suspension;
- rate limiting.

All cases passed.

## Pass 4 — Secure attachment and helper tests

Encrypted attachment storage was tested with PHP Sodium using:

- empty files;
- small binary files;
- exact 1 MiB files;
- multi-chunk files larger than 2 MiB;
- deliberate ciphertext tampering;
- persistent master-secret reuse;
- secure-storage denial files.

Round-trip hashes matched and tampering was rejected.

Additional executable tests verified:

- stale presence becomes offline;
- disabled presence/last-seen data is removed from REST-oriented user payloads;
- group mentions resolve only to conversation members;
- UTC message timestamps serialize correctly.

## Pass 5 — Structural and release integrity

Automated structural checks verify:

- every referenced RoleChat custom table exists in the installer schema;
- all 10 expected custom tables are defined;
- 23 REST route registrations exist;
- REST routes use permission callbacks;
- every API family used by the JavaScript client exists server-side;
- message content and attachment URLs are escaped before client-side HTML insertion;
- CSS braces are balanced;
- all include files are loaded by the plugin entry point;
- no unfinished-code placeholder markers remain.

The final ZIP is also re-opened and its file list/hash verified after creation.

## Environment limitation

The build environment used for this QA pass does not provide a running MySQL/MariaDB-backed WordPress installation or browser automation stack. Therefore these checks do not replace staging tests on the target WordPress hosting environment. Before production deployment, test activation, database migration, role combinations, REST requests, uploads/downloads, mobile layouts, and simultaneous multi-user messaging on a staging clone of the target site.
