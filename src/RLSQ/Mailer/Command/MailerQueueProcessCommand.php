<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Command;

use RLSQ\Console\Command\Command;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Input\InputOption;
use RLSQ\Console\Output\OutputInterface;
use RLSQ\Mailer\Mailer;

/**
 * Traite les emails en file d'attente.
 * Usage : php bin/console mailer:queue:process --limit=50
 */
class MailerQueueProcessCommand extends Command
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {
        parent::__construct('mailer:queue:process');
    }

    protected function configure(): void
    {
        $this->setDescription('Traite les emails en file d\'attente');
        $this->setHelp('Envoie les emails en attente dans la queue. Utilisez --limit pour limiter le nombre.');
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre max d\'emails à traiter', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $queue = $this->mailer->getQueue();

        if ($queue === null) {
            $output->writeln('Erreur : aucune queue configurée.');
            return self::FAILURE;
        }

        $pending = $queue->count();
        $output->writeln(sprintf('Emails en attente : %d', $pending));

        if ($pending === 0) {
            $output->writeln('Rien à traiter.');
            return self::SUCCESS;
        }

        $output->writeln(sprintf('Traitement de %d email(s)...', min($limit, $pending)));

        $sent = $this->mailer->processQueue($limit);

        $output->writeln(sprintf('Terminé : %d email(s) envoyé(s), %d restant(s).', $sent, $queue->count()));

        return self::SUCCESS;
    }
}
