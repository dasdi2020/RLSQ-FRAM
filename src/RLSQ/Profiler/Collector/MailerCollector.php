<?php

declare(strict_types=1);

namespace RLSQ\Profiler\Collector;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\Mailer\Mailer;
use RLSQ\Profiler\DataCollectorInterface;

/**
 * Collecte les emails envoyés et en queue pour le profiler.
 */
class MailerCollector implements DataCollectorInterface
{
    private array $data = [];

    public function __construct(
        private readonly Mailer $mailer,
    ) {}

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $log = $this->mailer->getLog();
        $queue = $this->mailer->getQueue();

        $sent = [];
        $queued = [];
        $failed = [];

        foreach ($log as $entry) {
            $email = $entry['email'];
            $item = [
                'id' => $email->getId(),
                'from' => $email->getFrom() ?? 'N/A',
                'to' => implode(', ', $email->getTo()),
                'subject' => $email->getSubject() ?? '(sans sujet)',
                'priority' => $email->getPriority(),
                'created_at' => $email->getCreatedAt()->format('H:i:s'),
                'status' => $entry['status'],
                'sent_at' => $entry['sent_at'],
                'error' => $entry['error'],
                'has_html' => $email->getHtml() !== null,
                'has_text' => $email->getText() !== null,
            ];

            match ($entry['status']) {
                'sent' => $sent[] = $item,
                'queued' => $queued[] = $item,
                default => $failed[] = $item,
            };
        }

        // Emails actuellement dans la queue (pas encore traités)
        $pendingInQueue = [];
        if ($queue !== null) {
            foreach ($queue->peek(20) as $email) {
                $pendingInQueue[] = [
                    'id' => $email->getId(),
                    'from' => $email->getFrom() ?? 'N/A',
                    'to' => implode(', ', $email->getTo()),
                    'subject' => $email->getSubject() ?? '(sans sujet)',
                    'priority' => $email->getPriority(),
                    'created_at' => $email->getCreatedAt()->format('H:i:s'),
                ];
            }
        }

        $this->data = [
            'sent' => $sent,
            'sent_count' => count($sent),
            'queued' => $queued,
            'queued_count' => count($queued),
            'failed' => $failed,
            'failed_count' => count($failed),
            'pending_in_queue' => $pendingInQueue,
            'pending_count' => $queue?->count() ?? 0,
            'transport' => $this->mailer->getTransport()->getName(),
            'total' => count($log),
        ];
    }

    public function getName(): string
    {
        return 'mailer';
    }

    public function getData(): array
    {
        return $this->data;
    }
}
