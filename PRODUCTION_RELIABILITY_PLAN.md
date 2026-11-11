# Production Reliability Plan

**Status:** Phase 1 (items 1–8), Phase 2 (items 9–17), and Phase 3 (items 18–21) are implemented. Phase 4 (optional item 22) is not started.

---

## Phase 1 — Blocking correctness (issues 1–8)

### 1. Outbound threadId

**`src/Clients/GmailClient.php`**

- Change `sendMessage(string $rawMime)` to `sendMessage(string $rawMime, ?string $threadId = null)`.
- Set `$message->setThreadId($threadId)` when non-empty.

**`src/Services/GmailService.php`**

- Pass `$params['threadId'] ?? null` to `client->sendMessage()`.
- Also fix the existing mismatch where `buildMime()` reads `$params['messageId']` while the docblock documents `firstMessageId`; support both keys.

### 2. In-Reply-To

**`src/Utils/MimeMessageBuilder.php`**

- Add `private readonly string $inReplyTo = ''` as the last constructor parameter.
- Emit `In-Reply-To:` when non-empty.

**`src/Services/GmailService.php`**

- Pass `inReplyTo: (string) ($params['inReplyTo'] ?? '')` from `buildMime()`.

### 3. Persist and expose RFC headers

Add new migration `database/migrations/20260101000011_add_gmail_headers_and_unique_indexes.php` (do not edit already-applied migrations):

- `google_messages`: `message_id` (string, nullable), `in_reply_to` (string, nullable), `references` (text, nullable).
- Add a lookup index on `message_id` (prefix-length index if needed for utf8mb4).

> **Note:** `references` is a MySQL reserved word, so it must be backticked in raw SQL.

**`src/Utils/GmailMessageParser.php`**

- Add `'references' => $headers['References'] ?? ''`.
- Document that `internalDate` is Gmail's millisecond epoch value, not seconds.

**`src/Repositories/MessageRepository.php`**

- Add `message_id`, `in_reply_to`, `references` to `fillable()`.

**`src/Services/GmailService.php`**

- Persist all three in `mapMessageRow()`.

**`src/Models/Message.php`**

- Add trailing constructor properties `messageId`, `inReplyTo`, `references`.
- Add them to `toArray()` / `fromArray()`.

**`README.md`**

- Document the `internalDate` units.

### 4. Base64url decoding

**`src/Clients/GmailClient.php::base64UrlDecode()`**

Use the remainder-based padding formula:

```php
$remainder = strlen($data) % 4;
if ($remainder !== 0) {
    $data .= str_repeat('=', 4 - $remainder);
}
$decoded = base64_decode(strtr($data, '-_', '+/'), true);
return $decoded === false ? '' : $decoded;
```

**`src/Utils/GmailMessageParser.php::urlSafeBase64Decode()`**

- Replace `?: ''` with `=== false ? '' : $decoded`, because `"0"` is a valid decode result and currently becomes empty.

### 5. Successful 401 retry

**`src/Clients/BaseClient.php`**

- Change `execute()` to accept an attempt counter (and retryability flag).
- On 401: `return $this->refreshAndRetry(...)` instead of ignoring the result.
- Guard against recursion: allow only one 401 refresh/retry per call.
- `refreshAndRetry()` should rebuild the service and re-enter `execute()` with `attempt + 1`.

### 6. String recipients

**`src/Utils/MimeMessageBuilder.php`**

- Change `to`, `cc`, `bcc`, `replyTo` constructor types from `array` to `mixed`.
- Build each header via `encodeRecipients()` and only emit it when the normalized value is non-empty.

**`src/Services/GmailService.php`**

- Ensure both `sendMessage()` and `createDraft()` use the same builder path and recipient normalization.

**`src/Validation/MessageValidator.php`**

- Validate normalized recipient addresses.

### 7. Attachment/sent-email unique indexes

In the new migration:

- Deduplicate existing `google_attachments` rows by `(google_id, message_google_id, user_id)` before adding the unique index.
- Add unique index `idx_att_google_msg_user`.
- Deduplicate `google_sent_emails` by `(message_google_id, user_id)` where non-null, then add a unique index.
- Leave the original create migrations untouched.

### 8. GMAIL_USER

**`src/Services/GmailService.php`**

- Construct `GmailClient` with `GoogleSuiteConfig::gmailUser()`.

**`src/Clients/GmailClient.php`**

- Fall back to `'me'` if the configured value is empty.

---

## Phase 2 — Production reliability (issues 9–17)

### 9. Parser robustness

**`src/Utils/GmailMessageParser.php`**

- Make header lookup case-insensitive.
- Add `decodeHeader()` using `iconv_mime_decode()` with `mb_decode_mimeheader()` fallback.
- Decode `Subject`, `From`, `To`, `Cc`, `Bcc`, `Reply-To`, `Message-ID`, `In-Reply-To`, `References`.
- Include attachment parts when `attachmentId` exists and either `filename` or `Content-ID` is present.
- Give unnamed inline parts a synthetic filename, e.g. `inline-<sanitized-content-id>`.
- Mark parts with `Content-ID` as inline where appropriate.
- Make `Content-ID`/`Content-Disposition` lookups case-insensitive.

### 10. From header

**`src/Utils/MimeMessageBuilder.php`**

- Omit `From:` entirely when `from` is empty instead of writing `From: me`.
- Use `encodeRecipients()` so only the display name is RFC 2047 encoded, not the address.

### 11. Attachment filename encoding

**`src/Utils/MimeMessageBuilder.php`**

- Add a filename-parameter helper.
- Escape `\` and `"` in ASCII quoted strings.
- Use RFC 2231 `filename*=UTF-8''...` and `name*=UTF-8''...` for non-ASCII names, with an ASCII fallback when possible.

### 12. OAuth state

**`src/Controller/OAuthController.php`**

- Add `bool $requireState = true` as the last constructor argument.
- When true, missing session/expected state is an error, not a bypass.
- When false, preserve the current CLI-friendly behavior.
- Update `scripts/get-auth-url.php` and `scripts/auth-callback.php` to opt out for CLI use.
- Document the flag in README.

### 13. Retry/backoff and batch isolation

**`src/Clients/BaseClient.php`**

- Add bounded exponential backoff with jitter for `429`, `500`, `502`, `503`, `504`.
- Keep 401 refresh behavior.
- Add an idempotent/retryable flag; do not auto-retry 429/503 for sends.

**`src/Clients/GmailClient.php`**

- Mark `sendMessage()` as non-retryable for rate-limit/server errors (401 still retried).

**`src/Services/GmailService.php`**

- Add `listMessageIds()` that returns IDs and pagination without fetching bodies.
- Make `listMessages()` catch per-message failures and return an errors map instead of aborting the whole batch.
- Expose `listMessageIds()` through the facade.

### 14. Label find-or-create

**`src/Services/GmailService.php`**

- Add `findLabelByName()`: refresh labels from Gmail and match by name.
- Add `getOrCreateLabel()`: return existing label or create it.
- Add facade convenience methods.

### 15. Token revocation

**`src/Auth/GoogleAuthManager.php`**

- Pass the refresh token explicitly:

```php
$client->revokeToken($payload['refresh_token'] ?? $payload['access_token'] ?? null);
```

- Remove the unnecessary refresh-before-revoke step.

### 16. Client secret storage

**`src/Repositories/AccessTokenRepository.php`**

- Stop writing `client_secret` in `saveToken()`; keep the parameter for backward compatibility but store `NULL`.

**`src/Auth/GoogleAuthManager.php`**

- Stop passing the secret into `saveToken()`.
- Document that `client_secret` remains in configuration only.

Optional cleanup SQL for existing `google_suite` rows can be included in the migration or documented.

### 17. MessageValidator

**`src/Validation/MessageValidator.php`**

- Require at least one of `body` or `html`.
- Validate `to`, `cc`, `bcc`, and `from` when present.
- Add a configurable attachment size limit via `GOOGLE_ATTACHMENT_MAX_BYTES` (default 25 MB, `0` disables).

**`src/Services/GmailService.php`**

- Avoid undefined `body` access when only HTML is supplied; use `body` or a `strip_tags(html)` fallback for the preview.

---

## Phase 3 — Packaging and documentation (issues 18–21)

### 18. Table prefix

- Document that migrations support only the default `google` prefix.
- Add a clear warning in README and `phinx.php` that custom `GOOGLE_TABLE_PREFIX` values require copying/adapting migrations.

### 19. Migration runner

Document the recommended app-side setup:

- Copy `database/migrations` into the app's `db/migrations`.
- Point Phinx at the app's existing PDO/config.
- Document the `DB_DSN` / `DB_USER` / `DB_PASSWORD` requirement for the package runner.

### 20. Composer/package

- Add `ext-mbstring`, `ext-fileinfo`, and `ext-iconv` to `require`.
- Remove `"minimum-stability": "dev"` (or limit it to development only).
- Add README install instructions for Packagist or VCS repository + pinned commit/tag.
- Tag `v1.0.0` once changes are verified.

### 21. README

Fix constructor examples to `new GoogleSuiteManager($pdo, $auth, $logger, $userId)`.

Document:

- Gmail-only vs all scopes.
- Mapping app config into package environment variables.
- `GMAIL_USER`.
- Table prefix behavior.
- Token storage/refresh and client-secret handling.
- `internalDate` units.
- `sendMessage()` recipient/thread/reference parameters.
- Migration setup for existing apps.
- OAuth state behavior for web vs CLI.

---

## Phase 4 — Optional ticket-use-case items (22)

Not required for correctness. Recommended follow-ups:

- `users.history.list` incremental sync with `historyId`.
- `markThreadRead()` convenience.
- Batch message fetching.
- Send idempotency/deduplication key.

`listMessageIds()` is included in Phase 2 because it directly addresses batch failure isolation.

---

## Tests to add/update

### MimeMessageBuilderTest

- `threadId` is not a MIME concern, but add `In-Reply-To`.
- String recipients work.
- `From` omitted when empty.
- Non-ASCII display name encodes only the name.
- Non-ASCII/quoted attachment filenames use safe encoding.

### GmailMessageParserTest

- RFC 2047 header decoding.
- Case-insensitive header lookup.
- `references` extraction.
- Inline attachment with `Content-ID` and no filename.
- Base64 decode of a value that decodes to `"0"`.

### MessageTest

- New header fields round-trip.

### MessageValidatorTest

- HTML-only message passes.
- Invalid `to`/`from` fails.
- Attachment size limit.

### Integration test

- Update the manually-created `google_messages` schema if needed.
- Verify token save no longer stores `client_secret`.

Run:

```bash
vendor/bin/phpunit
```

---

## Risks / decisions

- **New migration, not edits:** matches the request and avoids breaking already-applied migrations.
- **Unique indexes on existing data:** the migration must deduplicate first or it will fail.
- **`references` is reserved in MySQL:** always quote it.
- **OAuth state default true** is a behavior change for CLI callers; the example scripts will explicitly opt out.
- **Attachment size default:** 25 MB matches Gmail's practical limit; configurable and disableable.
- **No automatic 429/503 retry for sends:** avoids duplicate emails without an idempotency key.
- **Client secret column:** kept for backward compatibility but no longer written.
