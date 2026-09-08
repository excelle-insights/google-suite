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

```bash
composer require excelle-insights/google-suite:dev-main
```

## Environment Setup

Create a `.env` in your project root (or rely on the package `.env`):

```
GOOGLE_APP_NAME=Excelle Insights Google Suite
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET
GOOGLE_REDIRECT_URI=https://your-app.local/oauth/google-callback.php
GOOGLE_TABLE_PREFIX=google

# Database connection
DB_DSN=mysql:host=127.0.0.1;dbname=myapp
DB_USER=root
DB_PASSWORD=secret
```

> Note: `GOOGLE_CLIENT_SECRET` is a key in the JSON downloaded from the Google
> Console, not a secret file path. For testing while developing the package you
> can create the Google client IDs/secrets directly from your Google Cloud
> project's "OAuth 2.0 Client IDs" screen.

## Running Migrations

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

## Quick Start

### 1. Get the OAuth Authorization URL

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;
use ExcelleInsights\GoogleSuite\Controller\OAuthController;

$suite = new GoogleSuiteManager(null, null, dirname(__DIR__, 2));
$oauth = new OAuthController($suite);

echo $suite->getAuthorizationUrl();
```

### 2. Handle OAuth Callback

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;
use ExcelleInsights\GoogleSuite\Controller\OAuthController;

$suite = new GoogleSuiteManager();
$oauth = new OAuthController($suite);

echo json_encode($oauth->handleCallback());
```

### 3. Send an Email

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

$suite = new GoogleSuiteManager();

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