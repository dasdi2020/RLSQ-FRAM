<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\UploadedFile;

class UploadedFileTest extends TestCase
{
    public function testGetters(): void
    {
        $file = new UploadedFile('/tmp/upload123', 'rapport.pdf', 'application/pdf', UPLOAD_ERR_OK);

        $this->assertSame('/tmp/upload123', $file->getPath());
        $this->assertSame('rapport.pdf', $file->getOriginalName());
        $this->assertSame('pdf', $file->getOriginalExtension());
        $this->assertSame('application/pdf', $file->getMimeType());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testErrorFile(): void
    {
        $file = new UploadedFile('/tmp/err', 'big.zip', null, UPLOAD_ERR_INI_SIZE);

        $this->assertSame(UPLOAD_ERR_INI_SIZE, $file->getError());
    }
}
