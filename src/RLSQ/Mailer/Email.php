<?php

declare(strict_types=1);

namespace RLSQ\Mailer;

class Email
{
    private ?string $from = null;
    /** @var string[] */
    private array $to = [];
    /** @var string[] */
    private array $cc = [];
    /** @var string[] */
    private array $bcc = [];
    private ?string $replyTo = null;
    private ?string $subject = null;
    private ?string $text = null;
    private ?string $html = null;
    private int $priority = 3; // 1=highest, 5=lowest
    private ?\DateTimeImmutable $createdAt = null;
    private ?string $id = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->id = bin2hex(random_bytes(16));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function from(string $address): static
    {
        $this->from = $address;
        return $this;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function to(string ...$addresses): static
    {
        $this->to = array_merge($this->to, $addresses);
        return $this;
    }

    /** @return string[] */
    public function getTo(): array
    {
        return $this->to;
    }

    public function cc(string ...$addresses): static
    {
        $this->cc = array_merge($this->cc, $addresses);
        return $this;
    }

    /** @return string[] */
    public function getCc(): array
    {
        return $this->cc;
    }

    public function bcc(string ...$addresses): static
    {
        $this->bcc = array_merge($this->bcc, $addresses);
        return $this;
    }

    /** @return string[] */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function replyTo(string $address): static
    {
        $this->replyTo = $address;
        return $this;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function text(string $body): static
    {
        $this->text = $body;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function html(string $body): static
    {
        $this->html = $body;
        return $this;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function priority(int $priority): static
    {
        $this->priority = max(1, min(5, $priority));
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sérialise l'email pour le stockage en queue.
     */
    public function serialize(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'priority' => $this->priority,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Désérialise un email depuis un tableau.
     */
    public static function fromArray(array $data): static
    {
        $email = new static();
        $email->id = $data['id'] ?? $email->id;
        $email->from = $data['from'] ?? null;
        $email->to = $data['to'] ?? [];
        $email->cc = $data['cc'] ?? [];
        $email->bcc = $data['bcc'] ?? [];
        $email->replyTo = $data['reply_to'] ?? null;
        $email->subject = $data['subject'] ?? null;
        $email->text = $data['text'] ?? null;
        $email->html = $data['html'] ?? null;
        $email->priority = $data['priority'] ?? 3;
        $email->createdAt = isset($data['created_at'])
            ? new \DateTimeImmutable($data['created_at'])
            : new \DateTimeImmutable();

        return $email;
    }
}
