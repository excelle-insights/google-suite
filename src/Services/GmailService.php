<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Services;

use ExcelleInsights\GoogleSuite\Auth\GoogleAuthManager;
use ExcelleInsights\GoogleSuite\Auth\GoogleClientFactory;
use ExcelleInsights\GoogleSuite\Clients\GmailClient;
use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;
use ExcelleInsights\GoogleSuite\Contracts\LoggerInterface;
use ExcelleInsights\GoogleSuite\Exceptions\ResourceNotFoundException;
use ExcelleInsights\GoogleSuite\Exceptions\SyncException;
use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;
use ExcelleInsights\GoogleSuite\Models\Attachment;
use ExcelleInsights\GoogleSuite\Models\Label;
use ExcelleInsights\GoogleSuite\Models\Message;
use ExcelleInsights\GoogleSuite\Models\SentEmail;
use ExcelleInsights\GoogleSuite\Models\SyncStatus;
use ExcelleInsights\GoogleSuite\Repositories\AccessTokenRepository;
use ExcelleInsights\GoogleSuite\Repositories\AttachmentRepository;
use ExcelleInsights\GoogleSuite\Repositories\LabelRepository;
use ExcelleInsights\GoogleSuite\Repositories\MessageRepository;
use ExcelleInsights\GoogleSuite\Repositories\SentEmailRepository;
use ExcelleInsights\GoogleSuite\Utils\GmailMessageParser;
use ExcelleInsights\GoogleSuite\Utils\MimeMessageBuilder;
use ExcelleInsights\GoogleSuite\Validation\EmailValidator;
use ExcelleInsights\GoogleSuite\Validation\MessageValidator;
use Google\Service\Gmail;
use PDO;
use Throwable;

/**
 * Gmail operations (the primary service).
 *
 * Read operations pull from the Gmail API and cache results into the
 * local google_messages table. Write operations persist locally first,
 * then push to Gmail, marking the local row synced or failed.
 */
class GmailService extends AbstractService
{
    private GmailClient $client;
    private MessageRepository $messages;
    private LabelRepository $labels;
    private SentEmailRepository $sentEmails;
    private AttachmentRepository $attachments;

    public function __construct(
        private readonly PDO $pdo,
        GoogleAuthManager $authManager,
        ?LoggerInterface $logger = null,
        private readonly string|int $defaultUserId = 1,
    ) {
        parent::__construct($authManager, $logger);

        $factory = new GoogleClientFactory($authManager);
        $this->client      = new GmailClient($factory, (int) $this->defaultUserId, $this->logger);
        $this->messages    = new MessageRepository($pdo);
        $this->labels      = new LabelRepository($pdo);
        $this->sentEmails  = new SentEmailRepository($pdo);
        $this->attachments = new AttachmentRepository($pdo);
    }

    public function client(): GmailClient
    {
        return $this->client;
    }

    // ───────────────────────── Messages ──────────────────────────

    /**
     * List messages matching a Gmail query. Results are cached locally.
     *
     * @return array{gm_ids:array<int, string>, messages:array<int, Message>, nextPageToken:?string, resultSizeEstimate:int}
     */
    public function listMessages(string $query = '', int $maxResults = 100, string $pageToken = '', string|int $userId = 1): array
    {
        $response = $this->client->listMessages($query, $maxResults, $pageToken);

        $ids = [];
        $messages = [];
        foreach ($response->getMessages() as $record) {
            $gmailId = $record->getId();
            $ids[]   = $gmailId;
            $messages[] = $this->getMessage((string) $gmailId, $userId);
        }

        return [
            'gm_ids'              => $ids,
            'messages'            => $messages,
            'nextPageToken'       => $response->nextPageToken,
            'resultSizeEstimate'  => $response->getResultSizeEstimate(),
        ];
    }

    /**
     * Search messages using Gmail query syntax (e.g. "is:unread from:me").
     *
     * @return array<int, Message>
     */
    public function searchMessages(string $query, int $maxResults = 100, string|int $userId = 1): array
    {
        return $this->listMessages($query, $maxResults, '', $userId)['messages'];
    }

    /**
     * Fetch a single message by Gmail ID, cache it locally, return the model.
     */
    public function getMessage(string $messageId, string|int $userId = 1): Message
    {
        $gmailMessage = $this->client->getMessage($messageId, 'full');
        $parsed       = GmailMessageParser::parse($gmailMessage);

        $data = $this->mapMessageRow($parsed, (int) $userId, $messageId);
        $this->messages->upsert($data, ['google_id', 'user_id']);
        $row = $this->messages->findByGoogleId($messageId, (int) $userId);

        // Persist attachment metadata (bytes are fetched on demand).
        foreach ($parsed['attachments'] as $attachment) {
            $this->attachments->upsertForMessage(array_merge($attachment, [
                'message_google_id' => $messageId,
                'sync_status'       => 'pending',
            ]), (int) $userId);
        }

        return Message::fromArray($row ?? $data);
    }

    /**
     * Return the locally-cached message without hitting the API.
     */
    public function findLocalMessage(string $googleId, string|int $userId = 1): ?Message
    {
        $row = $this->messages->findByGoogleId($googleId, (int) $userId);
        return $row === null ? null : Message::fromArray($row);
    }

    // ───────────────────────── Send ──────────────────────────────

    /**
     * Send an email via the Gmail API.
     *
     * @param array{
     *   to:string|array, subject:string, body:string, html?:string,
     *   from?:string, cc?:string|array, bcc?:string|array,
     *   attachments?:array<int, array{path:string, filename?:string, mimeType?:string}>,
     *   threadId?:string, inReplyTo?:string, references?:array<int, string>,
     *   firstMessageId?:string
     * } $params
     */
    public function sendMessage(array $params, string|int $userId = 1): SentEmail
    {
        MessageValidator::validate($params);
        $attachments = MessageValidator::validateAttachments($params);

        $rowId = $this->sentEmails->insert([
            'user_id'         => (int) $userId,
            'to_address'      => EmailValidator::normalizeRecipients($params['to']),
            'cc_address'      => EmailValidator::normalizeRecipients($params['cc'] ?? []),
            'bcc_address'     => EmailValidator::normalizeRecipients($params['bcc'] ?? []),
            'subject'         => $params['subject'],
            'body_preview'    => mb_substr($params['body'], 0, 400),
            'has_attachments' => $attachments !== [] ? 1 : 0,
            'status'          => 'pending',
        ]);

        try {
            $raw = $this->buildMime($params, $attachments);
            $sent = $this->client->sendMessage($raw);
        } catch (Throwable $e) {
            $this->sentEmails->update((string) $rowId, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            throw $e instanceof SyncException
                ? $e
                : new SyncException('Failed to send email: ' . $e->getMessage(), 0, $e);
        }

        $this->sentEmails->markSent(
            (string) $rowId,
            (string) $sent->getId(),
            (string) $sent->getThreadId(),
        );

        $row = $this->sentEmails->find((string) $rowId);
        return SentEmail::fromArray($row ?? []);
    }

    /**
     * Create a draft (persist locally then push to Gmail).
     */
    public function createDraft(array $params, string|int $userId = 1): Message
    {
        MessageValidator::validate($params);
        $raw = $this->buildMime($params, MessageValidator::validateAttachments($params));

        $threadId = $params['threadId'] ?? null;
        $draft    = $this->client->createDraft($raw, $threadId);
        $message  = $draft->getMessage();

        if ($message === null || $message->getId() === '') {
            throw new SyncException('Draft created but returned no message id.');
        }

        $parsed = GmailMessageParser::parse($message);
        $data   = $this->mapMessageRow($parsed, (int) $userId, (string) $message->getId(), isDraft: true);
        $this->messages->upsert($data, ['google_id', 'user_id']);
        $row = $this->messages->findByGoogleId((string) $message->getId(), (int) $userId);

        return Message::fromArray($row ?? $data);
    }

    // ───────────────────────── Labels ────────────────────────────

    /**
     * List labels, caching them locally.
     *
     * @return array<int, Label>
     */
    public function listLabels(string|int $userId = 1): array
    {
        $response = $this->client->listLabels();
        $rows     = [];
        foreach ($response->getLabels() as $label) {
            $rows[] = $this->persistLabel($label, (int) $userId);
        }
        return $rows;
    }

    /**
     * Create a label. Requires gmail.labels scope.
     */
    public function createLabel(string $name, string $type = 'user', string|int $userId = 1): Label
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withErrors(['name' => 'Label name is required']);
        }
        if (!in_array($type, ['user', 'system'], true)) {
            throw ValidationException::withErrors(['type' => 'Label type must be "user" or "system"']);
        }

        $googleLabel = new Gmail\Label([
            'name' => $name,
            'labelListVisibility'    => 'labelShow',
            'messageListVisibility'  => 'show',
        ]);

        $created = $this->client->createLabel($googleLabel);
        return $this->persistLabel($created, (int) $userId);
    }

    public function deleteLabel(string $labelId, string|int $userId = 1): void
    {
        $this->client->deleteLabel($labelId);
        $row = $this->labels->findByGoogleId($labelId, (int) $userId);
        if ($row !== null) {
            $this->labels->delete((string) $row['id']);
        }
    }

    /**
     * Apply one or more labels to a message and refresh the local cache.
     */
    public function addLabelsToMessage(string $messageId, array $labelIds, string|int $userId = 1): Message
    {
        $this->client->modifyMessage($messageId, $labelIds, []);
        return $this->getMessage($messageId, $userId);
    }

    public function removeLabelsFromMessage(string $messageId, array $labelIds, string|int $userId = 1): Message
    {
        $this->client->modifyMessage($messageId, [], $labelIds);
        return $this->getMessage($messageId, $userId);
    }

    // ─────────────────────── Read state ──────────────────────────

    /**
     * Mark a message read (removes UNREAD).
     */
    public function markAsRead(string $messageId, string|int $userId = 1): Message
    {
        return $this->removeLabelsFromMessage($messageId, ['UNREAD'], $userId);
    }

    public function markAsUnread(string $messageId, string|int $userId = 1): Message
    {
        return $this->addLabelsToMessage($messageId, ['UNREAD'], $userId);
    }

    // ───────────────────────── Trash ─────────────────────────────

    public function trashMessage(string $messageId, string|int $userId = 1): Message
    {
        $this->client->trashMessage($messageId);
        $this->messages->updateByGoogleId($messageId, ['is_trashed' => 1], (int) $userId);
        return $this->getMessage($messageId, $userId);
    }

    public function untrashMessage(string $messageId, string|int $userId = 1): Message
    {
        $this->client->untrashMessage($messageId);
        $this->messages->updateByGoogleId($messageId, ['is_trashed' => 0], (int) $userId);
        return $this->getMessage($messageId, $userId);
    }

    /**
     * Permanently delete a message and its local cache.
     */
    public function deleteMessage(string $messageId, string|int $userId = 1): void
    {
        $this->client->deleteMessage($messageId);
        $this->messages->deleteByGoogleId($messageId, (int) $userId);
    }

    // ───────────────────────── Threads ───────────────────────────

    /**
     * List threads matching a query.
     *
     * @return array<int, array{id:string, snippet:string, messageCount:int, messages:array<int, Message>}>
     */
    public function listThreads(string $query = '', int $maxResults = 100, string|int $userId = 1): array
    {
        $response = $this->client->listThreads($query, $maxResults);

        $threads = [];
        foreach ($response->getThreads() as $record) {
            $thread = $this->client->getThread((string) $record->getId(), 'full');
            $threads[] = $this->mapThread($thread, (int) $userId);
        }
        return $threads;
    }

    /**
     * Fetch a single conversation thread with all its messages.
     *
     * @return array{id:string, snippet:string, messageCount:int, messages:array<int, Message>}
     */
    public function getThread(string $threadId, string|int $userId = 1): array
    {
        return $this->mapThread($this->client->getThread($threadId, 'full'), (int) $userId);
    }

    // ─────────────────────── Attachments ─────────────────────────

    /**
     * Download an attachment and return the decoded bytes.
     */
    public function getAttachment(string $messageId, string $attachmentId, string|int $userId = 1): string
    {
        return $this->client->getAttachment($messageId, $attachmentId);
    }

    /**
     * Download an attachment, persist the bytes to disk, and update the
     * local attachment row with the storage path.
     */
    public function saveAttachment(string $messageId, string $attachmentId, ?string $destinationDir = null, string|int $userId = 1): Attachment
    {
        $row = $this->attachments->findOneBy([
            'message_google_id' => $messageId,
            'google_id'         => $attachmentId,
        ]);
        if ($row === null) {
            throw new ResourceNotFoundException("Attachment $attachmentId not found for message $messageId");
        }

        $bytes = $this->getAttachment($messageId, $attachmentId, $userId);
        $dir   = $destinationDir ?? GoogleSuiteConfig::attachmentRoot() . '/' . (int) $userId . '/' . $messageId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new SyncException("Cannot create attachment directory: $dir");
        }

        $filename = (string) ($row['filename'] ?? 'attachment');
        $path     = $dir . '/' . preg_replace('/[^\w.\- ]+/u', '_', $filename);
        file_put_contents($path, $bytes);

        $this->attachments->update((string) $row['id'], [
            'storage_path' => $path,
            'sync_status'  => 'synced',
            'size'         => strlen($bytes),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $path = $row['storage_path'] ?? $path;
        return Attachment::fromArray(array_merge($row, [
            'storage_path' => $path,
            'size'         => strlen($bytes),
            'sync_status'  => 'synced',
        ]));
    }

    /**
     * List locally-cached attachments for a message.
     *
     * @return array<int, Attachment>
     */
    public function listAttachments(string $messageId, string|int $userId = 1): array
    {
        return array_map(
            fn (array $row) => Attachment::fromArray($row),
            $this->attachments->findByMessageId($messageId, (int) $userId),
        );
    }

    // ─────────────────────── Persistence ─────────────────────────

    /**
     * @param array<string, mixed> $parsed Result of GmailMessageParser::parse().
     * @return array<string, mixed>
     */
    private function mapMessageRow(array $parsed, int $userId, string $gmailId, bool $isDraft = false): array
    {
        $labelIds    = $parsed['label_ids'];
        $isRead      = !in_array('UNREAD', $labelIds, true);
        $isStarred   = in_array('STARRED', $labelIds, true);
        $isTrashed   = in_array('TRASH', $labelIds, true);

        return [
            'google_id'        => $gmailId,
            'thread_id'        => $parsed['thread_id'] !== '' ? $parsed['thread_id'] : null,
            'user_id'          => $userId,
            'subject'          => $parsed['subject'],
            'from_address'     => $parsed['from_address'],
            'from_name'        => $parsed['from_name'],
            'to_address'       => $parsed['to_address'],
            'cc_address'       => $parsed['cc_address'],
            'bcc_address'      => $parsed['bcc_address'],
            'reply_to_address' => $parsed['reply_to_address'],
            'snippet'          => $parsed['snippet'],
            'body_plain'       => $parsed['body_plain'],
            'body_html'        => $parsed['body_html'],
            'label_ids'        => json_encode($labelIds),
            'is_read'          => $isRead ? 1 : 0,
            'is_starred'       => $isStarred ? 1 : 0,
            'is_draft'         => ($isDraft || in_array('DRAFT', $labelIds, true)) ? 1 : 0,
            'is_trashed'       => $isTrashed ? 1 : 0,
            'has_attachments'  => $parsed['attachments'] !== [] ? 1 : 0,
            'internal_date'    => $parsed['internal_date'] !== 0 ? $parsed['internal_date'] : null,
            'size_estimate'    => $parsed['size_estimate'] !== 0 ? $parsed['size_estimate'] : null,
            'raw_payload'      => json_encode($this->rawPayload($gmailId)),
            'sync_status'      => SyncStatus::Synced->value,
            'synced_at'        => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array{path:string, filename:string, mimeType:string} $attachments
     */
    private function buildMime(array $params, array $attachments): string
    {
        $builder = new MimeMessageBuilder(
            to:      $params['to'],
            subject: $params['subject'],
            body:    $params['body'],
            html:    $params['html'] ?? '',
            from:    $params['from'] ?? '',
            cc:      $params['cc'] ?? [],
            bcc:     $params['bcc'] ?? [],
            messageId: isset($params['messageId']) ? (string) $params['messageId'] : '',
            references: isset($params['references']) && is_array($params['references']) ? $params['references'] : [],
            replyTo: isset($params['replyTo']) && is_array($params['replyTo']) ? array_values($params['replyTo']) : [],
        );

        if ($attachments !== []) {
            $builder->setAttachments($attachments);
        }
        return $builder->build();
    }

    private function persistLabel(Gmail\Label $googleLabel, int $userId): Label
    {
        $labelId  = (string) $googleLabel->getId();
        $color    = $googleLabel->getColor();
        $this->labels->upsert([
            'google_id'              => $labelId,
            'user_id'                => $userId,
            'name'                   => (string) $googleLabel->getName(),
            'type'                   => (string) $googleLabel->getType(),
            'message_list_visibility'=> $googleLabel->getMessageListVisibility(),
            'label_list_visibility'  => $googleLabel->getLabelListVisibility(),
            'color_background'       => $color?->getBackgroundColor(),
            'color_text'             => $color?->getTextColor(),
            'sync_status'            => 'synced',
        ], ['google_id', 'user_id']);

        $row = $this->labels->findByGoogleId($labelId, $userId);
        return Label::fromArray($row ?? ['google_id' => $labelId, 'name' => (string) $googleLabel->getName()]);
    }

    /**
     * @return array{id:string, snippet:string, messageCount:int, messages:array<int, Message>}
     */
    private function mapThread(Gmail\Thread $thread, int $userId): array
    {
        $messages = [];
        foreach ($thread->getMessages() as $m) {
            $messages[] = $this->getMessage((string) $m->getId(), $userId);
        }
        return [
            'id'            => (string) $thread->getId(),
            'snippet'       => (string) $thread->getSnippet(),
            'messageCount'  => count($messages),
            'messages'      => $messages,
        ];
    }

    private function rawPayload(string $gmailId): array
    {
        return ['id' => $gmailId, 'cached_at' => time()];
    }
}