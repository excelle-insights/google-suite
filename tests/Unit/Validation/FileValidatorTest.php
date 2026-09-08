<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Tests\Unit\Validation;

use ExcelleInsights\GoogleSuite\Exceptions\ValidationException;
use ExcelleInsights\GoogleSuite\Validation\FileValidator;
use PHPUnit\Framework\TestCase;

final class FileValidatorTest extends TestCase
{
    public function testValidUploadByPathPasses(): void
    {
        $path = sys_get_temp_dir() . '/google-suite-upload-' . uniqid() . '.bin';
        file_put_contents($path, 'data');

        try {
            $validated = FileValidator::validate([
                'name' => 'report.pdf',
                'path' => $path,
            ]);

            $this->assertSame('report.pdf', $validated['name']);
            $this->assertSame('application/octet-stream', $validated['mimeType']);
            $this->assertSame($path, $validated['source']);
        } finally {
            @unlink($path);
        }
    }

    public function testContentStringPasses(): void
    {
        $validated = FileValidator::validate([
            'name'   => 'notes.txt',
            'source' => 'hello world',
        ]);

        $this->assertSame('hello world', $validated['source']);
    }

    public function testMissingSourceThrows(): void
    {
        $this->expectException(ValidationException::class);
        FileValidator::validate(['name' => 'notes.txt']);
    }

    public function testMissingNameThrows(): void
    {
        $this->expectException(ValidationException::class);
        FileValidator::validate([
            'name'   => '',
            'source' => 'content',
        ]);
    }

    public function testNonExistentPathThrows(): void
    {
        $this->expectException(ValidationException::class);
        FileValidator::validate([
            'name' => 'missing.pdf',
            'path' => '/does/not/exist/missing.pdf',
        ]);
    }
}