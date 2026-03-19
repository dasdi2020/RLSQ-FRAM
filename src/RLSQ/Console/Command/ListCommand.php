<?php

declare(strict_types=1);

namespace RLSQ\Console\Command;

use RLSQ\Console\Application;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Output\OutputInterface;

class ListCommand extends Command
{
    public function __construct(
        private readonly Application $application,
    ) {
        parent::__construct('list');
    }

    protected function configure(): void
    {
        $this->setDescription('Liste toutes les commandes disponibles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%s %s', $this->application->getName(), $this->application->getVersion()));
        $output->writeln('');
        $output->writeln('Commandes disponibles :');

        $commands = $this->application->all();
        ksort($commands);

        // Calculer la largeur max pour l'alignement
        $maxLen = 0;
        foreach ($commands as $name => $cmd) {
            $maxLen = max($maxLen, strlen($name));
        }

        foreach ($commands as $name => $cmd) {
            $output->writeln(sprintf(
                '  %-' . ($maxLen + 2) . 's %s',
                $name,
                $cmd->getDescription(),
            ));
        }

        return self::SUCCESS;
    }
}
