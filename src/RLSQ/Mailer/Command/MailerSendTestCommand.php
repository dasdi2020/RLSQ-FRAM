<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Command;

use RLSQ\Console\Command\Command;
use RLSQ\Console\Input\InputArgument;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Input\InputOption;
use RLSQ\Console\Output\OutputInterface;
use RLSQ\Mailer\Email;
use RLSQ\Mailer\Mailer;

/**
 * Envoie un email de test.
 * Usage : php bin/console mailer:send-test user@example.com
 *         php bin/console mailer:send-test user@example.com --queue
 */
class MailerSendTestCommand extends Command
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {
        parent::__construct('mailer:send-test');
    }

    protected function configure(): void
    {
        $this->setDescription('Envoie un email de test');
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse email du destinataire');
        $this->addOption('queue', 'q', InputOption::VALUE_NONE, 'Mettre en queue au lieu d\'envoyer immédiatement');
        $this->addOption('subject', 's', InputOption::VALUE_REQUIRED, 'Sujet de l\'email', 'RLSQ-FRAM Test Email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = $input->getArgument('to');
        $subject = $input->getOption('subject');
        $useQueue = $input->getOption('queue');

        $email = (new Email())
            ->to($to)
            ->subject($subject)
            ->text("Ceci est un email de test envoyé par RLSQ-FRAM.\nDate : " . date('Y-m-d H:i:s'))
            ->html('<h1>RLSQ-FRAM</h1><p>Ceci est un <b>email de test</b>.</p><p>Date : ' . date('Y-m-d H:i:s') . '</p>');

        if ($useQueue) {
            $this->mailer->queue($email);
            $output->writeln(sprintf('Email mis en queue pour %s (ID: %s)', $to, substr($email->getId(), 0, 8)));
        } else {
            $result = $this->mailer->send($email);
            if ($result) {
                $output->writeln(sprintf('Email envoyé à %s (transport: %s)', $to, $this->mailer->getTransport()->getName()));
            } else {
                $output->writeln('Echec de l\'envoi.');
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
