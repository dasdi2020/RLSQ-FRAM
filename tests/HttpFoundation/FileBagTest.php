<?php

declare(strict_types=1);

namespace Tests\HttpFoundation;

use PHPUnit\Framework\TestCase;
use RLSQ\HttpFoundation\FileBag;
use RLSQ\HttpFoundation\UploadedFile;

class FileBagTest extends TestCase
{
    public function testSetWithUploadedFile(): void
    {
        $file = new UploadedFile('/tmp/test', 'photo.jpg', 'image/jpeg');
        $bag = new FileBag();
        $bag->set('avatar', $file);

        $this->assertTrue($bag->has('avatar'));
        $this->assertSame($file, $bag->get('avatar'));
    }

    public function testConvertFromPhpFiles(): void
    {
        $phpFile = [
            'name' => 'doc.pdf',
            'type' => 'application/pdf',
            'tmp_name' => '/tmp/php1234',
            'error' => UPLOAD_ERR_OK,
            'size' => 12345,
        ];

        $bag = new FileBag(['document' => $phpFile]);

        $file = $bag->get('document');
        $this->assertInstanceOf(UploadedFile::class, $file);
        $this->assertSame('doc.pdf', $file->getOriginalName());
        $this->assertSame('application/pdf', $file->getMimeType());
    }

    public function testConvertMultipleUploads(): void
    {
        $phpFiles = [
            'name' => ['a.jpg', 'b.png'],
            'type' => ['image/jpeg', 'image/png'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [100, 200],
        ];

        $bag = new FileBag(['photos' => $phpFiles]);

        $files = $bag->get('photos');
        $this->assertIsArray($files);
        $this->assertCount(2, $files);
        $this->assertSame('a.jpg', $files[0]->getOriginalName());
        $this->assertSame('b.png', $files[1]->getOriginalName());
    }

    public function testGetReturnsNull(): void
    {
        $bag = new FileBag();

        $this->assertNull($bag->get('missing'));
    }
}
