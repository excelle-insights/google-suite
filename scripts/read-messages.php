<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

/**
 * Step 4: Read Gmail messages.
 */

$suite = new GoogleSuiteManager(connectDb());

$page = $suite->listMessages('is:unread', 10);

foreach ($page['messages'] as $message) {
    printf(
        "[%s] %s — from %s%s",
        $message->isRead ? 'read' : 'unread',
        $message->subject !== '' ? $message->subject : '(no subject)',
        $message->fromAddress,
        PHP_EOL,
    );
}