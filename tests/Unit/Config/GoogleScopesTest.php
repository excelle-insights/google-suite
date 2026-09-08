<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Config;

use ExcelleInsights\GoogleSuite\Auth\GoogleScopes;
use PHPUnit\Framework\TestCase;

final class GoogleScopesTest extends TestCase
{
    public function testGmailFullIncludesReadSendModify(): void
    {
        $scopes = GoogleScopes::gmailFull();

        $this->assertContains('https://www.googleapis.com/auth/gmail.readonly', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/gmail.send', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/gmail.modify', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/gmail.labels', $scopes);
    }

    public function testCalendarFullIncludesCalendarAndEvents(): void
    {
        $scopes = GoogleScopes::calendarFull();

        $this->assertContains('https://www.googleapis.com/auth/calendar', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/calendar.events', $scopes);
    }

    public function testDriveFullIncludesDriveScope(): void
    {
        $this->assertContains('https://www.googleapis.com/auth/drive', GoogleScopes::driveFull());
    }

    public function testAllCombinesServicesAndUserEmail(): void
    {
        $scopes = GoogleScopes::all();

        $this->assertContains('https://www.googleapis.com/auth/userinfo.email', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/drive', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/calendar', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/gmail.modify', $scopes);
        $this->assertTrue(count($scopes) > 5);
    }
}