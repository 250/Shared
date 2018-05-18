<?php
declare(strict_types=1);

namespace ScriptFUSION\Steam250\Shared\Storage;

use League\Flysystem\Filesystem;
use ScriptFUSION\Type\StringType;

/**
 * Provides read/write storage that always writes to a dedicated write directory (or subdirectory thereof) and always
 * reads from a dedicated read directory. Files can be moved from the write directory to the read directory.
 */
class ReadWriteStorage
{
    private const READ_DIR = 'data';
    private const WRITE_DIR = 'building...';

    private const BASENAME = '$v["basename"]';
    private const FILENAME = '$v["filename"]';

    private const TYPE_FILE = 'file';
    private const TYPE_DIRECTORY = 'dir';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Downloads the specified file or directory contents.
     *
     * @param string $filespec File or directory path, separated by slashes ('/').
     *
     * @return bool True if all files were downloaded successfully, otherwise false.
     */
    public function download(string $filespec): bool
    {
        if (!$fileOrDirectory = $this->findLeafObject($filespec)) {
            throw new \RuntimeException("File not found in read directory: \"$filespec\".");
        }

        if ($this->isDirectory($fileOrDirectory)) {
            $files = $this->filesystem->listContents($fileOrDirectory);
        } else {
            $files = [$this->filesystem->getMetadata($fileOrDirectory)];
        }

        return from($files)
            // Only download files. Recursion not supported yet.
            ->where(static function (array $v): bool {
                return $v['type'] === self::TYPE_FILE;
            })
            ->all(function (array $v): bool {
                return (bool)file_put_contents($v['name'], $this->filesystem->read($v['path']));
            })
        ;
    }

    /**
     * Uploads the specified file or directory to the specified parent directory. Any existing files are overwritten.
     *
     * @param string $fileSpec Local file.
     * @param string $parent Optional. Parent directory.
     *
     * @return bool True if the file was uploaded successfully, otherwise false.
     */
    public function upload(string $fileSpec, string $parent = ''): bool
    {
        $directory = $this->createDirectories($parent);

        if (is_dir($fileSpec)) {
            $files = from(new \DirectoryIterator($fileSpec))
                ->where(static function (\DirectoryIterator $iterator): bool {
                    return $iterator->isFile();
                })
                ->select(static function (\DirectoryIterator $iterator): string {
                    return $iterator->getPathname();
                })
            ;
        } else {
            $files = [$fileSpec];
        }

        return from($files)->all(function ($filespec) use ($directory): bool {
            $filename = basename($filespec);

            // Find any existing file.
            $file = $this->findFile($filename, $directory);

            return $this->filesystem->put(
                $file['basename'] ?: "$directory/$filename",
                file_get_contents($filespec)
            );
        });
    }

    public function delete(string $file): bool
    {
        return $this->filesystem->delete($file);
    }

    /**
     * Creates one or more directories, separated by a slash ('/'), as required.
     * If directories already exist, no new directories will be created.
     *
     * @param string $directories
     *
     * @return string Leaf directory identifier.
     */
    public function createDirectories(string $directories): string
    {
        return $this->createDirectoriesArray(explode('/', $directories));
    }

    private function createDirectoriesArray(array $directories): string
    {
        $directories = array_merge(explode('/', self::WRITE_DIR), $directories);

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

    private function isDirectory(string $path): bool
    {
        return $this->filesystem->getMetadata($path)['type'] === self::TYPE_DIRECTORY;
    }

    /**
     * Finds a file with the specified name within the specified parent of the specified type.
     * If type is not specified, any type will match.
     *
     * @param string $filename File name.
     * @param string $parent Optional. Parent directory identifier.
     * @param string|null $type Optional. File type.
     *
     * @return array|null File metadata if found, otherwise null.
     */
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
        return $this->find($dirName, $parent, self::TYPE_FILE);
    }

    private function findDirectory(string $dirName, string $parent = ''): ?array
    {
        return $this->find($dirName, $parent, self::TYPE_DIRECTORY);
    }

    private function findLeafObject(string $filespec): ?string
    {
        $directories = array_merge(
            explode('/', self::READ_DIR),
            explode('/', $filespec)
        );

        $parent = '';

        do {
            $directory = array_shift($directories);

            if (!$response = $this->find($directory, $parent)) {
                return null;
            }

            $parent = $response['basename'];
        } while ($directories);

        return $parent;
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
        $fileInfo['vdir'] = self::READ_DIR . "/$yesterdayYearMonth/$yesterdayDay/$fileInfo[vdir]";

        return $fileInfo;
    }

    private function findRootDir(): string
    {
        return $this->findDirectory(self::READ_DIR)['basename'];
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
