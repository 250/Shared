<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Command;

use ScriptFUSION\Steam250\Shared\Storage\ReadWriteStorageFactory;
use ScriptFUSION\Steam250\Shared\Storage\StorageRoot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DownloadCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('download')
            ->setDescription('Downloads a file or directory.')
            ->addArgument('fileOrDirectory', InputArgument::REQUIRED, 'File or directory.')
            ->addOption('write-dir', 'w', InputOption::VALUE_NONE, 'Download from write directory.');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        return (int)!(new ReadWriteStorageFactory)->create()->download(
            $input->getArgument('fileOrDirectory'),
            $input->getOption('write-dir') ? StorageRoot::WRITE_DIR() : StorageRoot::READ_DIR()
        );
    }
}
