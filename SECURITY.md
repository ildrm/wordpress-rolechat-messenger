# RoleChat Messenger Security Notes

## Threat model

RoleChat handles private user-to-user communication, so its primary security boundaries are:

- authentication;
- role/capability authorization;
- directional sender/recipient authorization;
- conversation membership;
- group composition;
- attachment upload validation;
- moderation access;
- cross-site request protection;
- stored content output safety;
- abuse/rate limiting.

## Authorization invariant

The UI never decides whether a message is authorized. The server re-evaluates permissions for the authenticated user on every protected REST operation.

Direct and group sends require:

1. authenticated user;
2. chat-enabled role;
3. no active user suspension;
4. conversation membership;
5. active conversation;
6. no block in either direction;
7. directional role-matrix permission;
8. attachment permission where relevant;
9. per-user rate limit.

Group creation/member addition also validates role compatibility so a group cannot expose messages across a role direction disabled by policy.

## Uploads

Chat attachments are not exposed as ordinary public Media Library files. RoleChat validates the uploaded file with WordPress MIME/type rules, encrypts accepted content at rest with PHP Sodium secretstream (XChaCha20-Poly1305) using a persistent plugin-specific 256-bit master secret, stores ciphertext in a protected chat directory, and streams plaintext only after authenticated conversation authorization.

RoleChat additionally enforces:

- allowed extension list;
- configured byte-size limit;
- active-conversation attachment permission before accepting the upload;
- ownership metadata before a newly uploaded item can be attached to a message;
- nonce-protected authenticated download URLs;
- membership checks at download time;
- orphan attachment cleanup;
- tamper detection through authenticated encryption.

The attachment master secret is independent of WordPress authentication salts, so routine salt rotation does not invalidate existing encrypted chat files. It is retained when chat data is retained on uninstall and removed only when full data deletion is requested.

If PHP Sodium is unavailable, secure chat attachments are disabled rather than falling back to public storage.

## Moderation/privacy

Administrator-wide conversation inspection is disabled by default. If enabled, inspection actions are written to the audit log.

## Deployment responsibility

No application can be guaranteed vulnerability-free. Before a high-risk or high-volume production deployment, run the plugin through the site's normal staging, penetration-testing, backup, privacy, and incident-response procedures.
