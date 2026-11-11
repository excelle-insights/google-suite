# Excelle Insights Google Suite

A PHP package to integrate with **Google Gmail, Calendar and Drive**. Gmail is the
primary service; Calendar and Drive are fully supported secondaries.

---

## Project Structure

```
src/
├── Auth/                 # OAuth2 token handling, client + scope factory
│   ├── GoogleAuthManager.php
│   ├── GoogleClientFactory.php
│   └── GoogleScopes.php
├── Clients/              # Google API service clients
│   ├── BaseClient.php
│   ├── GmailClient.php
│   ├── CalendarClient.php
│   └── DriveClient.php
├── Config/               # Env-backed configuration
│   └── GoogleSuiteConfig.php
├── Contracts/            # Interfaces for DI
│   ├── HttpClientInterface.php
│   ├── LoggerInterface.php
│   └── ModelInterface.php
├── Controller/           # OAuth redirect/callback handling
│   └── OAuthController.php
├── Exceptions/           # GoogleSuiteException + specific subtypes
├── Facade/               # Main entry point
│   └── GoogleSuiteManager.php
├── Models/               # Domain models (Message, Label, Event, File, ...)
├── Repositories/         # PDO persistence layer
├── Services/             # Sync logic (local → Google + fetch/cache)
│   ├── GmailService.php
│   ├── CalendarService.php
│   └── DriveService.php
├── Support/              # EnvLoader, DatabaseLogger
├── Utils/                # MimeMessageBuilder, GmailMessageParser
└── Validation/           # MessageValidator, EventValidator, FileValidator
```

## Installation

### From Packagist

```bash
composer require excelle-insights/google-suite:^1.0
```

### From a VCS repository

If the package is not published to Packagist, add it as a VCS repository in
your application's `composer.json` and pin an exact tag or commit:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/excelle-insights/google-suite.git"
        }
    ],
    "require": {
        "excelle-insights/google-suite": "1.0.0"
    }
}
```

```bash
composer update excelle-insights/google-suite
```

Pinning `1.0.0` (or a full commit hash) keeps deployments reproducible. The
package requires PHP 8.1+ and the `fileinfo`, `iconv`, `json`, `mbstring`, and
`pdo` extensions.

## Environment Setup

Create a `.env` in your application root, or set the same keys in your app's
environment / config bootstrap. The package reads them through
`GoogleSuiteConfig`, so you can also populate `$_ENV` / `$_SERVER` directly.

```
GOOGLE_APP_NAME=Excelle Insights Google Suite
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET
GOOGLE_REDIRECT_URI=https://your-app.local/oauth/google-callback.php
GOOGLE_USER_ID=1

# Gmail user to operate on. "me" means the authenticated account.
GMAIL_USER=me

# Local table prefix. See the warning under "Running Migrations".
GOOGLE_TABLE_PREFIX=google

# Maximum size per attachment in bytes. 0 disables the limit.
GOOGLE_ATTACHMENT_MAX_BYTES=26214400

# Database connection used by the package's migration runner.
DB_DSN=mysql:host=127.0.0.1;dbname=myapp
DB_USER=root
DB_PASSWORD=secret
```

> Note: `GOOGLE_CLIENT_SECRET` is a key in the JSON downloaded from the Google
> Console, not a secret file path. For testing while developing the package you
> can create the Google client IDs/secrets directly from your Google Cloud
> project's "OAuth 2.0 Client IDs" screen.

If your application already loads configuration (for example a framework
`config/database.php`), map those values into the keys above during bootstrap:

```php
<?php
putenv('GOOGLE_CLIENT_ID=' . $config['google']['client_id']);
putenv('GOOGLE_CLIENT_SECRET=' . $config['google']['client_secret']);
putenv('GOOGLE_REDIRECT_URI=' . $config['google']['redirect_uri']);
putenv('DB_DSN=' . $config['db']['dsn']);
putenv('DB_USER=' . $config['db']['user']);
putenv('DB_PASSWORD=' . $config['db']['password']);
```

## OAuth Scopes

`GoogleAuthManager` requests `GoogleScopes::all()` by default: full Gmail,
Calendar, Drive, and `userinfo.email`.

If you only need Gmail, pass a narrower scope list:

```php
use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Auth\GoogleScopes;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

$auth = new GoogleAuthManager($pdo, scopes: GoogleScopes::gmailFull());
$suite = new GoogleSuiteManager($pdo, $auth, null, 1);
```

Requesting fewer scopes reduces the consent screen and the blast radius of a
compromised token.

## Running Migrations

### Recommended setup for an existing application

The package ships its migrations in `database/migrations`, but production apps
should run them from their own migration setup:

1. Copy `vendor/excelle-insights/google-suite/database/migrations` into your
   application's `db/migrations` directory.
2. Point your application's Phinx config (or your existing migration tool) at
   that directory and at your application's existing PDO connection.
3. Run your normal migration command, for example:

   ```bash
   vendor/bin/phinx migrate -c phinx.php
   ```

4. Apply new package migrations the same way on each upgrade. Do not edit
   migrations that have already run; add a new one instead.

### Package migration runner

For package development or a quick standalone setup, the bundled runner reads
`DB_DSN`, `DB_USER`, and `DB_PASSWORD` from the environment or `.env`:

```bash
php vendor/excelle-insights/google-suite/migrate.php
```

While developing the package itself:

```bash
php migrate.php --debug=true
```

Tables created include `api_access_tokens`, `http_request_logs`, `google_labels`,
`google_messages`, `google_sent_emails`, `google_attachments`, `google_calendars`,
`google_events`, `google_folders`, and `google_files`.

> **Table prefix warning:** the bundled migrations hard-code the default
> `google_` prefix (and the shared `api_access_tokens` / `http_request_logs`
> tables). They do **not** read `GOOGLE_TABLE_PREFIX`. Setting a custom prefix
> only changes the names the package queries at runtime — it does not rename
> existing tables. If you need a different prefix, copy the migrations into your
> app and adapt every table name before running them. Mixing a custom
> `GOOGLE_TABLE_PREFIX` with the unmodified migrations will make the package
> query tables that do not exist.

## Quick Start

All examples assume an existing PDO connection:

```php
<?php
require 'vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'root', 'secret');
$suite = new GoogleSuiteManager($pdo, null, null, 1);
```

The constructor signature is:

```php
new GoogleSuiteManager(
    PDO $pdo,
    ?GoogleAuthManager $auth = null,
    ?LoggerInterface $logger = null,
    string|int $defaultUserId = 1,
);
```

`$auth` and `$logger` are optional; pass them when you want custom scopes or
request logging. `$defaultUserId` scopes stored tokens and local rows to an
application user.

### 1. Get the OAuth Authorization URL

```php
<?php
require 'vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Controller\OAuthController;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

session_start();

$pdo   = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'root', 'secret');
$suite = new GoogleSuiteManager($pdo, null, null, 1);
$oauth = new OAuthController($suite, 1);

echo $oauth->getAuthUrl();
```

> `OAuthController` requires an active PHP session so it can issue and verify
> the OAuth `state` token. For CLI-only flows (for example the bundled
> `scripts/get-auth-url.php`), pass `requireState: false` to the constructor:
>
> ```php
> $oauth = new OAuthController($suite, 1, requireState: false);
> ```
>
> Web flows should leave the default (`true`) in place. A callback with a
> missing or mismatched `state` is rejected as a possible CSRF attempt.

### 2. Handle OAuth Callback

```php
<?php
require 'vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Controller\OAuthController;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

session_start();

$pdo   = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'root', 'secret');
$suite = new GoogleSuiteManager($pdo, null, null, 1);
$oauth = new OAuthController($suite, 1);

echo json_encode($oauth->handleCallback());
```

### 3. Send an Email

```php
<?php
require 'vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

$pdo   = new PDO('mysql:host=127.0.0.1;dbname=myapp', 'root', 'secret');
$suite = new GoogleSuiteManager($pdo, null, null, 1);

$result = $suite->sendMessage([
    'to'      => 'client@example.com',
    'cc'      => ['finance@example.com' => 'Finance'],
    'subject' => 'Excelle Insights — Project Update',
    'body'    => "Hi,\n\nHere is the update you asked for.",
    'html'    => '<p>Hi,</p><p>Here is the update you asked for.</p>',
    'attachments' => [
        ['path' => '/tmp/report.pdf', 'filename' => 'report.pdf'],
    ],
]);

echo "Sent. Gmail message ID: " . $result->messageGoogleId;
```

`sendMessage()` accepts:

| Key | Type | Notes |
| --- | --- | --- |
| `to` | string or array | A single address, a list, or `['email@example.com' => 'Display Name']` |
| `cc`, `bcc` | string or array | Same formats as `to` |
| `from` | string | Optional; omitted from the MIME headers when empty |
| `subject` | string | Required |
| `body` | string | Plain-text body. At least one of `body` / `html` is required |
| `html` | string | Optional HTML body |
| `attachments` | array | Each item: `path`, optional `filename`, `mimeType` |
| `threadId` | string | Gmail thread to attach the message to (for replies) |
| `inReplyTo` | string | RFC `In-Reply-To` header (the parent's `Message-ID`) |
| `references` | string[] | RFC `References` chain |
| `messageId` / `firstMessageId` | string | RFC `Message-ID` to set on the outgoing message |

For replies, pass `threadId`, `inReplyTo`, and `references` so Gmail and mail
clients keep the conversation together.

> Attachments are checked against `GOOGLE_ATTACHMENT_MAX_BYTES`
> (default 25 MB; set to `0` to disable the limit).

### 4. Read & Search Messages

```php
$unread = $suite->listMessages('is:unread', 25);

$messages = $suite->searchMessages('from:client@example.com subject:"invoice"');

foreach ($messages as $message) {
    echo $message->subject . ' — ' . $message->fromAddress . PHP_EOL;
}
```

### 5. Manage Labels & Read State

```php
$suite->markAsRead($messageId);
$suite->markAsUnread($messageId);
$suite->addLabelsToMessage($messageId, ['IMPORTANT', 'WORK']);
$suite->createLabel('To Follow');
```

### 6. Calendar & Drive

```php
$suite->createEvent([
    'summary' => 'Client Meeting',
    'start'   => '2026-02-05 09:00:00',
    'end'     => '2026-02-05 10:00:00',
    'timeZone'=> 'Africa/Nairobi',
    'attendees' => [['email' => 'client@example.com', 'name' => 'Client']],
]);

$suite->uploadFile([
    'name'     => 'report.pdf',
    'path'     => '/tmp/report.pdf',
    'parentId' => '1ABC...folderId',
]);
```

## Tokens and Client Secrets

- Tokens are stored in `api_access_tokens` as a JSON payload, scoped by
  application name and user id.
- `GoogleAuthManager` refreshes expired access tokens automatically and
  preserves the refresh token across refreshes.
- `GOOGLE_CLIENT_SECRET` is read from configuration at runtime and is **never**
  written to the database. The legacy `api_access_tokens.client_secret` column
  is kept for backward compatibility but new writes store `NULL`.
- `revokeToken()` revokes the stored refresh token at Google and then removes
  the local row.

## Testing

```bash
vendor/bin/phpunit tests
```

Ensure your `.env` is configured with valid database credentials and that you
have completed the OAuth handshake for at least the integration tests.

## Features

- Automatic OAuth2 authentication with Google (offline refresh tokens).
- Full Gmail support: read/search, send (with HTML + attachments), labels,
  read-state, trash/untrash, threads, and attachments.
- Calendar support: events CRUD with attendees/reminders/recurrence plus
  calendar listing and creation.
- Drive support: files and folders CRUD, upload/download, and query search.
- Local persistence with status-backed sync (`pending`/`synced`/`failed`) and
  retry tracking, mirroring the QuickBooks package.

## Notes

- `internal_date` (and `Message::$internalDate`) stores Gmail's `internalDate`
  value, which is a Unix timestamp in **milliseconds**, not seconds. Divide by
  1000 before passing it to `date()` or `DateTimeImmutable`.
- `GMAIL_USER` controls which mailbox the Gmail client operates on. It defaults
  to `me` (the authenticated account); set it to an address only when the
  authenticated user has delegated access to that mailbox.
- `GOOGLE_TABLE_PREFIX` only affects runtime table lookups. The bundled
  migrations always create `google_`-prefixed tables; see "Running Migrations".
- Outbound replies should pass `threadId`, `inReplyTo`, and optionally
  `references` to `sendMessage()` so Gmail and mail clients keep the RFC
  conversation headers intact.