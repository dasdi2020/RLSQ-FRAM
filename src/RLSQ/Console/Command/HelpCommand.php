<?php

declare(strict_types=1);

namespace RLSQ\Console\Command;

use RLSQ\Console\Application;
use RLSQ\Console\Input\InputArgument;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Output\OutputInterface;

class HelpCommand extends Command
{
    public function __construct(
        private readonly Application $application,
    ) {
        parent::__construct('help');
    }

    protected function configure(): void
    {
        $this->setDescription('Affiche l\'aide d\'une commande');
        $this->addArgument('command_name', InputArgument::OPTIONAL, 'Le nom de la commande', 'help');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandName = $input->getArgument('command_name') ?? 'help';

        if (!$this->application->has($commandName)) {
            $output->writeln(sprintf('La commande "%s" n\'existe pas.', $commandName));
            return self::FAILURE;
        }

        $command = $this->application->get($commandName);

        $output->writeln(sprintf('Commande : %s', $command->getName()));
        $output->writeln(sprintf('Description : %s', $command->getDescription()));

        $help = $command->getHelp();
        if ($help !== '' && $help !== $command->getDescription()) {
            $output->writeln('');
            $output->writeln($help);
        }

        $definition = $command->getDefinition();

        if (!empty($definition->getArguments())) {
            $output->writeln('');
            $output->writeln('Arguments :');
            foreach ($definition->getArguments() as $arg) {
                $required = $arg->isRequired() ? ' (requis)' : '';
                $output->writeln(sprintf('  %-20s %s%s', $arg->getName(), $arg->getDescription(), $required));
            }
        }

        if (!empty($definition->getOptions())) {
            $output->writeln('');
            $output->writeln('Options :');
            foreach ($definition->getOptions() as $opt) {
                $shortcut = $opt->getShortcut() ? sprintf('-%s, ', $opt->getShortcut()) : '    ';
                $output->writeln(sprintf('  %s--%-16s %s', $shortcut, $opt->getName(), $opt->getDescription()));
            }
        }

        return self::SUCCESS;
    }
}
