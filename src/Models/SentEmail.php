<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class SentEmail implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $messageGoogleId = null,
        public readonly ?string $threadId = null,
        public readonly int $userId = 1,
        public readonly ?string $toAddress = null,
        public readonly ?string $ccAddress = null,
        public readonly ?string $bccAddress = null,
        public readonly ?string $subject = null,
        public readonly ?string $bodyPreview = null,
        public readonly bool $hasAttachments = false,
        public readonly string $status = 'pending',
        public readonly ?string $errorMessage = null,
        public readonly ?string $sentAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'message_google_id'     => $this->messageGoogleId,
            'thread_id'             => $this->threadId,
            'user_id'               => $this->userId,
            'to_address'            => $this->toAddress,
            'cc_address'            => $this->ccAddress,
            'bcc_address'           => $this->bccAddress,
            'subject'               => $this->subject,
            'body_preview'          => $this->bodyPreview,
            'has_attachments'       => $this->hasAttachments,
            'status'                => $this->status,
            'error_message'         => $this->errorMessage,
            'sent_at'               => $this->sentAt,
            'created_at'            => $this->createdAt,
            'updated_at'            => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id:                 $data['id'] ?? null,
            messageGoogleId:    $data['message_google_id'] ?? null,
            threadId:           $data['thread_id'] ?? null,
            userId:             (int) ($data['user_id'] ?? 1),
            toAddress:          $data['to_address'] ?? null,
            ccAddress:          $data['cc_address'] ?? null,
            bccAddress:         $data['bcc_address'] ?? null,
            subject:            $data['subject'] ?? null,
            bodyPreview:        $data['body_preview'] ?? null,
            hasAttachments:     (bool) ($data['has_attachments'] ?? false),
            status:             $data['status'] ?? 'pending',
            errorMessage:       $data['error_message'] ?? null,
            sentAt:             $data['sent_at'] ?? null,
            createdAt:          $data['created_at'] ?? null,
            updatedAt:          $data['updated_at'] ?? null,
        );
    }
}