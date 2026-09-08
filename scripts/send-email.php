<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

/**
 * Step 3: Send an email through Gmail (the primary integration).
 */

$suite = new GoogleSuiteManager(connectDb());

$result = $suite->sendMessage([
    'to'      => 'client@example.com',
    'subject' => 'Excelle Insights — Project Update',
    'body'    => "Hi,\n\nHere is the update you asked for.\n\nBest,\nExcelle",
    'html'    => '<p>Hi,</p><p>Here is the <b>update</b> you asked for.</p>',
]);

if ($result->status === 'sent') {
    echo 'Sent. Gmail message ID: ' . $result->messageGoogleId . PHP_EOL;
} else {
    echo 'Queued locally (pending send).' . PHP_EOL;
}