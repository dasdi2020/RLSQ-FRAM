<?php

declare(strict_types=1);

namespace Tests\Mailer;

use PHPUnit\Framework\TestCase;
use RLSQ\Mailer\Email;
use RLSQ\Mailer\Queue\FilesystemQueue;

class FilesystemQueueTest extends TestCase
{
    private string $queueDir;

    protected function setUp(): void
    {
        $this->queueDir = sys_get_temp_dir() . '/rlsq_queue_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->queueDir)) {
            foreach (glob($this->queueDir . '/*') as $f) { unlink($f); }
            rmdir($this->queueDir);
        }
    }

    public function testEnqueueAndDequeue(): void
    {
        $queue = new FilesystemQueue($this->queueDir);

        $email = (new Email())->from('a@b.com')->to('c@d.com')->subject('FS Test');
        $queue->enqueue($email);

        $this->assertSame(1, $queue->count());

        $dequeued = $queue->dequeue();
        $this->assertNotNull($dequeued);
        $this->assertSame('FS Test', $dequeued->getSubject());
        $this->assertSame('a@b.com', $dequeued->getFrom());

        $this->assertSame(0, $queue->count());
    }

    public function testPeekDoesNotRemove(): void
    {
        $queue = new FilesystemQueue($this->queueDir);

        $queue->enqueue((new Email())->subject('Peek'));
        $peeked = $queue->peek();

        $this->assertCount(1, $peeked);
        $this->assertSame(1, $queue->count());
    }

    public function testClear(): void
    {
        $queue = new FilesystemQueue($this->queueDir);

        $queue->enqueue((new Email())->subject('A'));
        $queue->enqueue((new Email())->subject('B'));

        $queue->clear();
        $this->assertSame(0, $queue->count());
    }

    public function testCreatesDirectory(): void
    {
        $this->assertDirectoryDoesNotExist($this->queueDir);

        $queue = new FilesystemQueue($this->queueDir);

        $this->assertDirectoryExists($this->queueDir);
    }
}
