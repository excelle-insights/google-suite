<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Clients;

use ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use Google\Service\Gmail;

/**
 * Gmail API client (primary service).
 *
 * All methods operate on the authenticated user ("me" by default) and
 * return decoded Google response objects. Base64url encoding/decoding is
 * handled here so callers work with plain MIME strings and bytes.
 */
class GmailClient extends BaseClient
{
    public function __construct(
        GoogleClientFactory $clientFactory,
        int $userId = 1,
        ?LoggerInterface $logger = null,
        string $user = 'me',
    ) {
        parent::__construct($clientFactory, $userId, $logger);
        $this->user = $user;
    }

    private string $user;

    protected function serviceClass(): string
    {
        return Gmail::class;
    }

    // ───────────────────────── Messages ──────────────────────────

    /**
     * List messages matching a Gmail query.
     *
     * @return Gmail\ListMessagesResponse
     */
    public function listMessages(string $query = '', int $maxResults = 100, string $pageToken = ''): Gmail\ListMessagesResponse
    {
        $params = [
            'maxResults'      => max(1, min(500, $maxResults)),
            'includeSpamTrash' => false,
        ];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }

        return $this->execute(
            fn () => $this->service()->users_messages->listUsersMessages($this->user, $params),
            'users.messages.list',
        );
    }

    /**
     * Fetch a single message with optional format (full|metadata|minimal|raw).
     *
     * @return Gmail\Message
     */
    public function getMessage(string $messageId, string $format = 'full'): Gmail\Message
    {
        return $this->execute(
            fn () => $this->service()->users_messages->get($this->user, $messageId, ['format' => $format]),
            'users.messages.get',
        );
    }

    /**
     * Send a message built from a raw RFC2822 MIME string.
     *
     * @return Gmail\Message The sent message (id + threadId).
     */
    public function sendMessage(string $rawMime): Gmail\Message
    {
        $message = new Gmail\Message(['raw' => $this->base64UrlEncode($rawMime)]);
        return $this->execute(
            fn () => $this->service()->users_messages->send($this->user, $message),
            'users.messages.send',
        );
    }

    /**
     * Create a draft from a raw RFC2822 MIME string.
     *
     * @return Gmail\Draft
     */
    public function createDraft(string $rawMime, ?string $threadId = null): Gmail\Draft
    {
        $message = new Gmail\Message(['raw' => $this->base64UrlEncode($rawMime)]);
        if ($threadId !== null) {
            $message->setThreadId($threadId);
        }
        $draft = new Gmail\Draft(['message' => $message]);
        return $this->execute(
            fn () => $this->service()->users_drafts->create($this->user, $draft),
            'users.drafts.create',
        );
    }

    /**
     * Add/remove labels on a message.
     */
    public function modifyMessage(string $messageId, array $addLabelIds = [], array $removeLabelIds = []): Gmail\Message
    {
        $body = new Gmail\ModifyMessageRequest();
        if ($addLabelIds !== []) {
            $body->setAddLabelIds(array_values($addLabelIds));
        }
        if ($removeLabelIds !== []) {
            $body->setRemoveLabelIds(array_values($removeLabelIds));
        }
        return $this->execute(
            fn () => $this->service()->users_messages->modify($this->user, $messageId, $body),
            'users.messages.modify',
        );
    }

    public function trashMessage(string $messageId): Gmail\Message
    {
        return $this->execute(
            fn () => $this->service()->users_messages->trash($this->user, $messageId),
            'users.messages.trash',
        );
    }

    public function untrashMessage(string $messageId): Gmail\Message
    {
        return $this->execute(
            fn () => $this->service()->users_messages->untrash($this->user, $messageId),
            'users.messages.untrash',
        );
    }

    public function deleteMessage(string $messageId): void
    {
        $this->execute(
            fn () => $this->service()->users_messages->delete($this->user, $messageId),
            'users.messages.delete',
        );
    }

    /**
     * Fetch one attachment (decoded bytes).
     */
    public function getAttachment(string $messageId, string $attachmentId): string
    {
        $part = $this->execute(
            fn () => $this->service()->users_messages_attachments->get($this->user, $messageId, $attachmentId),
            'users.messages.attachments.get',
        );
        return $this->base64UrlDecode((string) $part->getData());
    }

    // ───────────────────────── Labels ────────────────────────────

    /**
     * List all labels.
     *
     * @return Gmail\ListLabelsResponse
     */
    public function listLabels(): Gmail\ListLabelsResponse
    {
        return $this->execute(
            fn () => $this->service()->users_labels->listUsersLabels($this->user),
            'users.labels.list',
        );
    }

    public function getLabel(string $labelId): Gmail\Label
    {
        return $this->execute(
            fn () => $this->service()->users_labels->get($this->user, $labelId),
            'users.labels.get',
        );
    }

    public function createLabel(Gmail\Label $label): Gmail\Label
    {
        return $this->execute(
            fn () => $this->service()->users_labels->create($this->user, $label),
            'users.labels.create',
        );
    }

    public function updateLabel(string $labelId, Gmail\Label $label): Gmail\Label
    {
        return $this->execute(
            fn () => $this->service()->users_labels->update($this->user, $labelId, $label),
            'users.labels.update',
        );
    }

    public function deleteLabel(string $labelId): void
    {
        $this->execute(
            fn () => $this->service()->users_labels->delete($this->user, $labelId),
            'users.labels.delete',
        );
    }

    // ───────────────────────── Threads ────────────────────────────

    /**
     * List threads matching a query.
     *
     * @return Gmail\ListThreadsResponse
     */
    public function listThreads(string $query = '', int $maxResults = 100, string $pageToken = ''): Gmail\ListThreadsResponse
    {
        $params = ['maxResults' => max(1, min(500, $maxResults))];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }
        return $this->execute(
            fn () => $this->service()->users_threads->listUsersThreads($this->user, $params),
            'users.threads.list',
        );
    }

    /**
     * Fetch a thread with all its messages.
     */
    public function getThread(string $threadId, string $format = 'full'): Gmail\Thread
    {
        return $this->execute(
            fn () => $this->service()->users_threads->get($this->user, $threadId, ['format' => $format]),
            'users.threads.get',
        );
    }

    /**
     * RFC 4648 "URL and Filename safe" base64 without padding.
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($padded, true) ?: '';
    }
}