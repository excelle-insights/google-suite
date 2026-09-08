<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Contracts;

/**
 * Contract for all domain models so they can round-trip between local
 * database rows and Google API payloads.
 *
 * @template T
 */
interface ModelInterface
{
    /**
     * Return the model as an associative array suitable for DB/API use.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Build a model from an associative array.
     *
     * @param array<string, mixed> $data
     * @return T
     */
    public static function fromArray(array $data): self;
}