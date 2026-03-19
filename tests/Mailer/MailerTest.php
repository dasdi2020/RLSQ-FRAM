<?php

declare(strict_types=1);

namespace Tests\Mailer;

use PHPUnit\Framework\TestCase;
use RLSQ\Mailer\Email;
use RLSQ\Mailer\Mailer;
use RLSQ\Mailer\Queue\InMemoryQueue;
use RLSQ\Mailer\Transport\NullTransport;

class MailerTest extends TestCase
{
    // --- Email ---

    public function testEmailBuilder(): void
    {
        $email = (new Email())
            ->from('sender@test.com')
            ->to('alice@test.com', 'bob@test.com')
            ->cc('cc@test.com')
            ->bcc('bcc@test.com')
            ->replyTo('reply@test.com')
            ->subject('Hello')
            ->text('Text body')
            ->html('<h1>HTML body</h1>')
            ->priority(1);

        $this->assertSame('sender@test.com', $email->getFrom());
        $this->assertSame(['alice@test.com', 'bob@test.com'], $email->getTo());
        $this->assertSame(['cc@test.com'], $email->getCc());
        $this->assertSame(['bcc@test.com'], $email->getBcc());
        $this->assertSame('reply@test.com', $email->getReplyTo());
        $this->assertSame('Hello', $email->getSubject());
        $this->assertSame('Text body', $email->getText());
        $this->assertSame('<h1>HTML body</h1>', $email->getHtml());
        $this->assertSame(1, $email->getPriority());
        $this->assertNotEmpty($email->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $email->getCreatedAt());
    }

    public function testEmailSerialize(): void
    {
        $email = (new Email())
            ->from('a@b.com')
            ->to('c@d.com')
            ->subject('Test')
            ->text('Body');

        $data = $email->serialize();

        $this->assertSame($email->getId(), $data['id']);
        $this->assertSame('a@b.com', $data['from']);
        $this->assertSame(['c@d.com'], $data['to']);
    }

    public function testEmailFromArray(): void
    {
        $email = Email::fromArray([
            'id' => 'abc123',
            'from' => 'x@y.com',
            'to' => ['z@w.com'],
            'subject' => 'Restored',
            'text' => 'Restored body',
            'priority' => 2,
        ]);

        $this->assertSame('abc123', $email->getId());
        $this->assertSame('x@y.com', $email->getFrom());
        $this->assertSame('Restored', $email->getSubject());
        $this->assertSame(2, $email->getPriority());
    }

    // --- Mailer send ---

    public function testSendImmediate(): void
    {
        $mailer = new Mailer(new NullTransport());

        $email = (new Email())
            ->from('a@b.com')
            ->to('c@d.com')
            ->subject('Test');

        $result = $mailer->send($email);

        $this->assertTrue($result);
        $this->assertCount(1, $mailer->getLog());
        $this->assertSame('sent', $mailer->getLog()[0]['status']);
    }

    public function testDefaultFrom(): void
    {
        $mailer = new Mailer(new NullTransport());
        $mailer->setDefaultFrom('default@app.com');

        $email = (new Email())->to('user@test.com')->subject('Hello');
        $mailer->send($email);

        $this->assertSame('default@app.com', $email->getFrom());
    }

    // --- Queue ---

    public function testQueueEmail(): void
    {
        $queue = new InMemoryQueue();
        $mailer = new Mailer(new NullTransport(), $queue);

        $email = (new Email())->from('a@b.com')->to('c@d.com')->subject('Queued');
        $mailer->queue($email);

        $this->assertSame(1, $queue->count());
        $this->assertCount(1, $mailer->getLog());
        $this->assertSame('queued', $mailer->getLog()[0]['status']);
    }

    public function testProcessQueue(): void
    {
        $queue = new InMemoryQueue();
        $mailer = new Mailer(new NullTransport(), $queue);

        for ($i = 0; $i < 5; $i++) {
            $mailer->queue((new Email())->from('a@b.com')->to('c@d.com')->subject("Email $i"));
        }

        $this->assertSame(5, $queue->count());

        $sent = $mailer->processQueue(3);

        $this->assertSame(3, $sent);
        $this->assertSame(2, $queue->count());
    }

    public function testProcessQueueAll(): void
    {
        $queue = new InMemoryQueue();
        $mailer = new Mailer(new NullTransport(), $queue);

        $mailer->queue((new Email())->from('a@b.com')->to('c@d.com')->subject('One'));
        $mailer->queue((new Email())->from('a@b.com')->to('c@d.com')->subject('Two'));

        $sent = $mailer->processQueue(100);

        $this->assertSame(2, $sent);
        $this->assertSame(0, $queue->count());
    }

    public function testQueuePriority(): void
    {
        $queue = new InMemoryQueue();
        $mailer = new Mailer(new NullTransport(), $queue);

        $mailer->queue((new Email())->from('a@b.com')->to('c@d.com')->subject('Low')->priority(5));
        $mailer->queue((new Email())->from('a@b.com')->to('c@d.com')->subject('High')->priority(1));
        $mailer->queue((new Email())->from('a@b.com')->to('c@d.com')->subject('Normal')->priority(3));

        $first = $queue->peek(1)[0];
        $this->assertSame('High', $first->getSubject());
    }

    public function testQueueThrowsWithoutQueue(): void
    {
        $mailer = new Mailer(new NullTransport()); // No queue

        $this->expectException(\LogicException::class);
        $mailer->queue((new Email())->to('a@b.com'));
    }

    // --- InMemoryQueue ---

    public function testInMemoryQueueOperations(): void
    {
        $queue = new InMemoryQueue();

        $this->assertSame(0, $queue->count());

        $queue->enqueue((new Email())->subject('A'));
        $queue->enqueue((new Email())->subject('B'));

        $this->assertSame(2, $queue->count());

        $first = $queue->dequeue();
        $this->assertSame('A', $first->getSubject());
        $this->assertSame(1, $queue->count());

        $peeked = $queue->peek();
        $this->assertCount(1, $peeked);
        $this->assertSame(1, $queue->count()); // peek ne retire pas

        $queue->clear();
        $this->assertSame(0, $queue->count());
    }

    public function testDequeueEmptyReturnsNull(): void
    {
        $queue = new InMemoryQueue();

        $this->assertNull($queue->dequeue());
    }
}
