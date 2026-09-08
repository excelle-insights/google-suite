<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ExcelleInsights\GoogleSuite\Controller\OAuthController;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

/**
 * Step 2: Handle the OAuth callback.
 *
 * Google redirects back to GOOGLE_REDIRECT_URI with ?code=... and ?state=...
 * This script swaps the code for tokens and stores them locally.
 */

$suite = new GoogleSuiteManager(connectDb());
$oauth = new OAuthController($suite);

$result = $oauth->handleCallback();

header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);