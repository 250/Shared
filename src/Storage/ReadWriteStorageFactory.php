<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Storage;

use Hypweb\Flysystem\GoogleDrive\GoogleDriveAdapter;
use League\Flysystem\Filesystem;

final class ReadWriteStorageFactory
{
    public function create(): ReadWriteStorage
    {
        $client = new \Google_Client([
            'client_id' => '459694133763-lm5r957uf766sfste7g7h9btkb75tdkf.apps.googleusercontent.com',
            'client_secret' => $_SERVER['GOOGLE_CLIENT_SECRET'],
        ]);

        $client->fetchAccessTokenWithRefreshToken($_SERVER['GOOGLE_REFRESH_TOKEN']);

        return new ReadWriteStorage(
            new Filesystem(
                new GoogleDriveAdapter(
                    new \Google_Service_Drive($client)
                )
            )
        );
    }
}
