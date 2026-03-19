<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Command;

use RLSQ\Console\Command\Command;
use RLSQ\Console\Helper\Table;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Input\InputOption;
use RLSQ\Console\Output\OutputInterface;
use RLSQ\Mailer\Mailer;

/**
 * Affiche le statut de la file d'attente des emails.
 * Usage : php bin/console mailer:queue:status
 */
class MailerQueueStatusCommand extends Command
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {
        parent::__construct('mailer:queue:status');
    }

    protected function configure(): void
    {
        $this->setDescription('Affiche le statut de la file d\'attente des emails');
        $this->addOption('peek', 'p', InputOption::VALUE_OPTIONAL, 'Nombre d\'emails à afficher', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $this->mailer->getQueue();

        if ($queue === null) {
            $output->writeln('Aucune queue configurée.');
            return self::FAILURE;
        }

        $count = $queue->count();
        $output->writeln(sprintf('Transport : %s', $this->mailer->getTransport()->getName()));
        $output->writeln(sprintf('Emails en attente : %d', $count));
        $output->writeln('');

        if ($count === 0) {
            $output->writeln('La file d\'attente est vide.');
            return self::SUCCESS;
        }

        $limit = (int) ($input->getOption('peek') ?? 10);
        $emails = $queue->peek($limit);

        $table = new Table($output);
        $table->setHeaders(['ID (8 car.)', 'De', 'A', 'Sujet', 'Priorite', 'Date']);

        foreach ($emails as $email) {
            $table->addRow([
                substr($email->getId(), 0, 8),
                $email->getFrom() ?? 'N/A',
                implode(', ', $email->getTo()),
                mb_substr($email->getSubject() ?? '', 0, 40),
                $email->getPriority(),
                $email->getCreatedAt()->format('H:i:s'),
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
