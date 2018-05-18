<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Command;

use ScriptFUSION\Steam250\Shared\Storage\ReadWriteStorageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UploadCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('upload')
            ->setDescription('Uploads a file or directory.')
            ->addArgument('source', InputArgument::REQUIRED, 'Source file or directory.')
            ->addArgument('destination', InputArgument::OPTIONAL, 'Destination directory.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        return (int)!(new ReadWriteStorageFactory)->create()->upload(
            $input->getArgument('source'),
            $input->getArgument('destination')
        );
    }
}
