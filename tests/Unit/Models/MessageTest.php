<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Models;

use ExcelleInsights\GoogleSuite\Models\Label;
use ExcelleInsights\GoogleSuite\Models\Message;
use ExcelleInsights\GoogleSuite\Models\SyncStatus;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testConstructorShapesModel(): void
    {
        $message = new Message(
            googleId:      'message-123',
            subject:       'Hello',
            fromAddress:   'alice@example.com',
            isRead:        true,
            internalDate:  1788837000,
            syncStatus:    SyncStatus::Synced,
        );

        $this->assertSame('message-123', $message->googleId);
        $this->assertTrue($message->isRead);
        $this->assertSame(SyncStatus::Synced, $message->syncStatus);
    }

    public function testToArrayUsesSnakeCaseColumns(): void
    {
        $message = new Message(
            googleId:    'm1',
            userId:      7,
            fromAddress: 'alice@example.com',
            labelIds:    ['IMPORTANT', 'WORK'],
        );

        $array = $message->toArray();

        $this->assertSame('m1', $array['google_id']);
        $this->assertSame(7, $array['user_id']);
        $this->assertSame(['IMPORTANT', 'WORK'], $array['label_ids']);
    }

    public function testFromArrayRoundTrip(): void
    {
        $message = Message::fromArray([
            'id'          => 42,
            'google_id'   => 'g-42',
            'thread_id'   => 't-9',
            'user_id'     => 3,
            'subject'     => 'Re: update',
            'is_read'     => 1,
            'sync_status' => 'synced',
        ]);

        $this->assertSame('42', $message->id);
        $this->assertSame('g-42', $message->googleId);
        $this->assertSame(3, $message->userId);
        $this->assertTrue($message->isRead);
        $this->assertSame(SyncStatus::Synced, $message->syncStatus);
    }

    public function testInvalidSyncStatusFallsBackToPending(): void
    {
        $message = Message::fromArray(['sync_status' => 'wat']);

        $this->assertSame(SyncStatus::Pending, $message->syncStatus);
    }

    public function testRfcHeaderFieldsRoundTrip(): void
    {
        $message = Message::fromArray([
            'message_id'  => '<child@example.com>',
            'in_reply_to' => '<parent@example.com>',
            'references'  => '<root@example.com> <parent@example.com>',
        ]);

        $this->assertSame('<child@example.com>', $message->messageId);
        $this->assertSame('<parent@example.com>', $message->inReplyTo);
        $this->assertSame('<root@example.com> <parent@example.com>', $message->references);

        $array = $message->toArray();
        $this->assertSame('<child@example.com>', $array['message_id']);
        $this->assertSame('<parent@example.com>', $array['in_reply_to']);
        $this->assertSame('<root@example.com> <parent@example.com>', $array['references']);
    }

    public function testLabelRoundTrip(): void
    {
        $label = Label::fromArray([
            'google_id'      => 'label-1',
            'name'           => 'Work',
            'type'           => 'user',
            'color_background' => '#ff0000',
        ]);

        $array = $label->toArray();

        $this->assertSame('label-1', $label->googleId);
        $this->assertSame('Work', $label->name);
        $this->assertSame('#ff0000', $array['color_background']);
    }

    public function testSyncStatusEnum(): void
    {
        $this->assertSame('pending', SyncStatus::Pending->value);
        $this->assertSame('synced', SyncStatus::Synced->value);
        $this->assertSame('failed', SyncStatus::Failed->value);
        $this->assertSame(SyncStatus::Synced, SyncStatus::tryFrom('synced'));
    }
}