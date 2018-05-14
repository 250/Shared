<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Storage;

use League\Flysystem\Filesystem;
use ScriptFUSION\Type\StringType;

class OnlineStorage
{
    public const ROOT_DIR = 'data';

    private const BASENAME = '$v["basename"]';
    private const FILENAME = '$v["filename"]';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function list(string $directory): array
    {
        return $this->filesystem->listContents($directory);
    }

    public function download(string $file): string
    {
        return $this->filesystem->read($file);
    }

    /**
     * Uploads the specified file to the specified parent directory. If the file already exists it is overwritten.
     *
     * @param string $fileSpec Local file.
     * @param string $parent Optional. Parent directory.
     *
     * @return bool True if the file was uploaded successfully, otherwise false.
     */
    public function upload(string $fileSpec, string $parent = ''): bool
    {
        $filename = basename($fileSpec);

        // Find any existing file.
        $file = $this->findFile($filename, $parent);

        return $this->filesystem->put(
            $file['basename'] ?: "$parent/$filename",
            file_get_contents($fileSpec)
        );
    }

    public function delete(string $file): bool
    {
        return $this->filesystem->delete($file);
    }

    public function makeDirectoryRecursively(string $directories): string
    {
        return $this->makeDirectoryArrayRecursively(explode('/', $directories));
    }

    private function makeDirectoryArrayRecursively(array $directories): string
    {
        $parent = '';

        do {
            $directory = array_shift($directories);

            if ($response = $this->findDirectory($directory, $parent)) {
                $parent = $response['basename'];

                continue;
            }

            if (!$this->filesystem->createDir($make = "$parent/$directory")) {
                throw new \RuntimeException("Failed to create directory: \"$make\".");
            }

            $parent = $this->findDirectory($directory, $parent)['basename'];
        } while ($directories);

        return $parent;
    }

    private function find(string $filename, string $parent = '', string $type = null): ?array
    {
        return from($files = $this->filesystem->listContents($parent))
            ->where(static function (array $v) use ($filename, $type): bool {
                if ($type !== null && $v['type'] !== $type) {
                    return false;
                }

                return $v['name'] === $filename;
            })
            ->singleOrDefault()
        ;
    }

    private function findFile(string $dirName, string $parent = ''): ?array
    {
        return $this->find($dirName, $parent, 'file');
    }

    private function findDirectory(string $dirName, string $parent = ''): ?array
    {
        return $this->find($dirName, $parent, 'dir');
    }

    public function fetchLatestDatabaseSnapshot(): array
    {
        $dataDir = $this->findRootDir();

        $yearMonthDir = from($files = $this->filesystem->listContents($dataDir))
            ->where(static function (array $v): bool {
                return StringType::startsWith($v['filename'], '20');
            })
            ->orderByDescending(self::FILENAME)
            ->first()
        ;

        $dayDir = from($files = $this->filesystem->listContents($yearMonthDir['basename']))
            ->orderByDescending(self::FILENAME)
            ->first()
        ;

        $fileInfo = $this->findLatestBuildDatabaseSnapshot($dayDir['basename']);
        $fileInfo['vdir'] = "data/$yearMonthDir[filename]/$dayDir[filename]/$fileInfo[vdir]";

        return $fileInfo;
    }

    public function fetchYesterdaysLastDatabaseSnapshot(): array
    {
        $dataDir = $this->findRootDir();

        $yearMonthData = from($files = $this->filesystem->listContents($dataDir))
            ->where(static function (array $v): bool {
                return StringType::startsWith($v['filename'], '20');
            })
            ->orderByDescending(self::FILENAME)
            ->first()
        ;

        $day = from($files = $this->filesystem->listContents($yearMonthData['basename']))
            ->orderByDescending(self::FILENAME)
            ->select(self::FILENAME)
            ->first()
        ;

        $yesterday = new \DateTimeImmutable("$yearMonthData[filename]$day -1day");
        $yesterdayYearMonth = $yesterday->format('Ym');
        $yesterdayDay = $yesterday->format('d');

        $yearMonthDir = from($files = $this->filesystem->listContents($dataDir))
            ->where(static function (array $v) use ($yesterdayYearMonth): bool {
                return $v['filename'] === $yesterdayYearMonth;
            })
            ->select(self::BASENAME)
            ->single()
        ;

        $dayDir = from($files = $this->filesystem->listContents($yearMonthDir))
            ->where(static function (array $v) use ($yesterdayDay): bool {
                return $v['filename'] === $yesterdayDay;
            })
            ->select(self::BASENAME)
            ->first()
        ;

        $fileInfo = $this->findLatestBuildDatabaseSnapshot($dayDir);
        $fileInfo['vdir'] = self::ROOT_DIR . "/$yesterdayYearMonth/$yesterdayDay/$fileInfo[vdir]";

        return $fileInfo;
    }

    private function findRootDir(): string
    {
        return $this->findDirectory(self::ROOT_DIR)['basename'];
    }

    /**
     * Downloads the latest build from the specified day directory.
     *
     * @param string $dayDir Directory name for the day of the month.
     *
     * @return array
     */
    private function findLatestBuildDatabaseSnapshot(string $dayDir): array
    {
        $buildDir = from($files = $this->filesystem->listContents($dayDir))
            ->orderByDescending('$v["filename"]')
            ->first()
        ;

        return from($files = $this->filesystem->listContents($buildDir['basename']))
            ->where(static function (array $v): bool {
                return $v['name'] === 'steam.sqlite';
            })
            ->single() + ['vdir' => $buildDir['filename']]
        ;
    }
}
