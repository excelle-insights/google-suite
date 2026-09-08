<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Models;

use ExcelleInsights\GoogleSuite\Contracts\ModelInterface;

class Label implements ModelInterface
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $googleId = null,
        public readonly int $userId = 1,
        public readonly string $name = '',
        public readonly ?string $type = null,
        public readonly ?string $messageListVisibility = null,
        public readonly ?string $labelListVisibility = null,
        public readonly ?string $colorBackground = null,
        public readonly ?string $colorText = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                       => $this->id,
            'google_id'                => $this->googleId,
            'user_id'                  => $this->userId,
            'name'                     => $this->name,
            'type'                     => $this->type,
            'message_list_visibility'  => $this->messageListVisibility,
            'label_list_visibility'    => $this->labelListVisibility,
            'color_background'         => $this->colorBackground,
            'color_text'               => $this->colorText,
            'created_at'               => $this->createdAt,
            'updated_at'               => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id:                      $data['id'] ?? null,
            googleId:                $data['google_id'] ?? null,
            userId:                  (int) ($data['user_id'] ?? 1),
            name:                    $data['name'] ?? '',
            type:                    $data['type'] ?? null,
            messageListVisibility:   $data['message_list_visibility'] ?? null,
            labelListVisibility:     $data['label_list_visibility'] ?? null,
            colorBackground:         $data['color_background'] ?? null,
            colorText:               $data['color_text'] ?? null,
            createdAt:               $data['created_at'] ?? null,
            updatedAt:               $data['updated_at'] ?? null,
        );
    }
}