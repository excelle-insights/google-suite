<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class Message implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly ?string $threadId = null,
        public readonly int $userId = 1,
        public readonly ?string $subject = null,
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
        public readonly ?string $toAddress = null,
        public readonly ?string $ccAddress = null,
        public readonly ?string $bccAddress = null,
        public readonly ?string $replyToAddress = null,
        public readonly ?string $snippet = null,
        public readonly ?string $bodyPlain = null,
        public readonly ?string $bodyHtml = null,
        /** @var array<int, string>|null */
        public readonly ?array $labelIds = null,
        public readonly bool $isRead = false,
        public readonly bool $isStarred = false,
        public readonly bool $isDraft = false,
        public readonly bool $isTrashed = false,
        public readonly bool $hasAttachments = false,
        public readonly ?int $internalDate = null,
        public readonly ?int $sizeEstimate = null,
        public readonly ?string $rawPayload = null,
        public readonly SyncStatus $syncStatus = SyncStatus::Pending,
        public readonly ?string $errorMessage = null,
        public readonly ?string $syncedAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $messageId = null,
        public readonly ?string $inReplyTo = null,
        public readonly ?string $references = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'google_id'        => $this->googleId,
            'thread_id'        => $this->threadId,
            'user_id'          => $this->userId,
            'subject'          => $this->subject,
            'from_address'     => $this->fromAddress,
            'from_name'        => $this->fromName,
            'to_address'       => $this->toAddress,
            'cc_address'       => $this->ccAddress,
            'bcc_address'      => $this->bccAddress,
            'reply_to_address' => $this->replyToAddress,
            'message_id'       => $this->messageId,
            'in_reply_to'      => $this->inReplyTo,
            'references'       => $this->references,
            'snippet'          => $this->snippet,
            'body_plain'       => $this->bodyPlain,
            'body_html'        => $this->bodyHtml,
            'label_ids'        => $this->labelIds,
            'is_read'          => $this->isRead,
            'is_starred'       => $this->isStarred,
            'is_draft'         => $this->isDraft,
            'is_trashed'       => $this->isTrashed,
            'has_attachments'  => $this->hasAttachments,
            'internal_date'    => $this->internalDate,
            'size_estimate'    => $this->sizeEstimate,
            'raw_payload'      => $this->rawPayload,
            'sync_status'      => $this->syncStatus->value,
            'error_message'    => $this->errorMessage,
            'synced_at'        => $this->syncedAt,
            'created_at'       => $this->createdAt,
            'updated_at'       => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        $labelIds = $data['label_ids'] ?? null;
        if (is_string($labelIds)) {
            $labelIds = json_decode($labelIds, true) ?: [];
        }

        return new static(
            id:               isset($data['id']) ? (string) $data['id'] : null,
            googleId:         $data['google_id'] ?? null,
            threadId:         $data['thread_id'] ?? null,
            userId:           (int) ($data['user_id'] ?? 1),
            subject:          $data['subject'] ?? null,
            fromAddress:      $data['from_address'] ?? null,
            fromName:         $data['from_name'] ?? null,
            toAddress:        $data['to_address'] ?? null,
            ccAddress:        $data['cc_address'] ?? null,
            bccAddress:       $data['bcc_address'] ?? null,
            replyToAddress:   $data['reply_to_address'] ?? null,
            messageId:        $data['message_id'] ?? null,
            inReplyTo:        $data['in_reply_to'] ?? null,
            references:       $data['references'] ?? null,
            snippet:          $data['snippet'] ?? null,
            bodyPlain:        $data['body_plain'] ?? null,
            bodyHtml:         $data['body_html'] ?? null,
            labelIds:         $labelIds,
            isRead:           (bool) ($data['is_read'] ?? false),
            isStarred:        (bool) ($data['is_starred'] ?? false),
            isDraft:          (bool) ($data['is_draft'] ?? false),
            isTrashed:        (bool) ($data['is_trashed'] ?? false),
            hasAttachments:   (bool) ($data['has_attachments'] ?? false),
            internalDate:     isset($data['internal_date']) ? (int) $data['internal_date'] : null,
            sizeEstimate:     isset($data['size_estimate']) ? (int) $data['size_estimate'] : null,
            rawPayload:       $data['raw_payload'] ?? null,
            syncStatus:       SyncStatus::tryFrom($data['sync_status'] ?? 'pending') ?? SyncStatus::Pending,
            errorMessage:     $data['error_message'] ?? null,
            syncedAt:         $data['synced_at'] ?? null,
            createdAt:        $data['created_at'] ?? null,
            updatedAt:        $data['updated_at'] ?? null,
        );
    }
}