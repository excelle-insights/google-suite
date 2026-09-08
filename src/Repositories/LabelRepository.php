<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use ExcelleInsights\GoogleSuite\Config\GoogleSuiteConfig;

/**
 * Persistence for Gmail labels in the google_labels table.
 */
class LabelRepository extends AbstractRepository
{
    protected function table(): string
    {
        return GoogleSuiteConfig::table('labels');
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function fillable(): array
    {
        return [
            'google_id', 'user_id', 'name', 'type', 'message_list_visibility',
            'label_list_visibility', 'color_background', 'color_text',
            'created_at', 'updated_at',
        ];
    }

    public function findByGoogleId(string $googleId, int $userId = 1): ?array
    {
        return $this->findOneBy(['google_id' => $googleId, 'user_id' => $userId]);
    }

    public function findByName(string $name, int $userId = 1): ?array
    {
        return $this->findOneBy(['name' => $name, 'user_id' => $userId]);
    }

    /**
     * Upsert a batch of labels returned by the Gmail API.
     *
     * @param array<int, array<string, mixed>> $labels
     */
    public function syncLabels(int $userId, array $labels): void
    {
        foreach ($labels as $label) {
            $googleId = $label['google_id'] ?? $label['id'] ?? null;
            if ($googleId === null) {
                continue;
            }
            $this->upsert(array_merge([
                'google_id' => $googleId,
                'user_id'   => $userId,
            ], $label), ['google_id', 'user_id']);
        }
    }
}