<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Command;

use ScriptFUSION\Steam250\Shared\Storage\ReadWriteStorageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DownloadLastTwoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('download-last2')
            ->setDescription('Downloads the two database snapshots from the last two consecutive days.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        (new ReadWriteStorageFactory)->create()->downloadLastTwoSnapshots();

        return 0;
    }
}
