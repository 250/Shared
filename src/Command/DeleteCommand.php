<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Command;

use ScriptFUSION\Steam250\Shared\Storage\ReadWriteStorageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('delete')
            ->setDescription('Deletes an uploaded file or files matching a pattern.')
            ->addArgument('file', InputArgument::REQUIRED, 'File path.')
            ->addOption('pattern', 'p', InputOption::VALUE_REQUIRED, 'File pattern.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $storage = (new ReadWriteStorageFactory)->create();

        if ($pattern = $input->getOption('pattern')) {
            return (int)!$storage->deletePattern($input->getArgument('file'), $pattern);
        }

        return (int)!$storage->delete($input->getArgument('file'));
    }
}
