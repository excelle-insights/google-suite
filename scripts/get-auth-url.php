<?php

/**
 * Step 1: Generate the Google OAuth authorization URL and render the
 * "Connect Google" button.
 *
 *   php scripts/get-auth-url.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\GoogleSuite\Controller\OAuthController;
use ExcelleInsights\GoogleSuite\Facade\GoogleSuiteManager;

$suite = new GoogleSuiteManager(connectDb());
$oauth = new OAuthController($suite, 1, false);

$authUrl = $oauth->getAuthUrl();

echo "$authUrl\n";