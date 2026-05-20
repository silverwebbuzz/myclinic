<?php

declare(strict_types=1);

namespace Tests;

use App\Support\UploadValidator;
use PHPUnit\Framework\TestCase;

final class UploadValidatorTest extends TestCase
{
    public function testUuidFilenameHasExtension(): void
    {
        $name = UploadValidator::uuidFilename('png');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $name);
    }

    public function testValidateRejectsOversizedFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mc');
        file_put_contents($tmp, str_repeat('x', 100));
        $file = [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $tmp,
            'size' => 100,
        ];

        $ext = UploadValidator::validate($file, ['text/plain' => 'txt'], 50);
        $this->assertNull($ext);
        @unlink($tmp);
    }
}
