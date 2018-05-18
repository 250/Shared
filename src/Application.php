<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared;

use ScriptFUSION\Steam250\Shared\Command\DownloadCommand;
use ScriptFUSION\Steam250\Shared\Command\UploadCommand;

final class Application
{
    private $app;

    public function __construct()
    {
        $this->app = $app = new \Symfony\Component\Console\Application;

        $app->addCommands([
            new DownloadCommand,
            new UploadCommand,
        ]);
    }

    public function start(): int
    {
        return $this->app->run();
    }
}
