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
        if (!$fileOrDirectoryPath = $this->findLeafObject($filespec)) {
            throw new \RuntimeException("File not found in read directory: \"$filespec\".");
        }

        if ($this->isDirectory($fileOrDirectoryPath)) {
            $files = $this->filesystem->listContents($fileOrDirectoryPath);
        } else {
            $files = [$this->filesystem->getMetadata($fileOrDirectoryPath)];
        }

        return from($files)
            // Only download files. Recursion not supported yet.
            ->where(\Closure::fromCallable([__CLASS__, 'isFile']))
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

    /**
     * Moves a file or directory from the write root to the read root.
     *
     * @param string $filespec File or directory path, separated by slashes ('/').
     *
     * @return bool True if all files were moved successfully, otherwise false.
     */
    public function moveUploadedFile(string $filespec): bool
    {
        if (!$fileOrDirectoryPath = $this->findLeafObject($filespec, self::WRITE_DIR)) {
            throw new \RuntimeException("Cannot move file \"$filespec\": not found.");
        }

        $directories = self::filespecToDirectoryList($filespec);

        if ($this->isDirectory($fileOrDirectoryPath)) {
            $files = $this->filesystem->listContents($fileOrDirectoryPath);
        } else {
            $files = [$this->filesystem->getMetadata($fileOrDirectoryPath)];

            // Discard file name.
            array_pop($directories);
        }

        // Mirror directory structure at destination.
        $destinationId = $this->createDirectoriesArray($directories, self::READ_DIR);

        // Move files.
        if (!from($files)
            // Only download files. Recursion not supported yet.
            ->where(\Closure::fromCallable([__CLASS__, 'isFile']))
            ->all(function (array $v) use ($destinationId): bool {
                // Find any existing file and delete it.
                if ($file = $this->findFile($v['name'], $destinationId)) {
                    // We have to delete because renaming to existing file ID just deletes the source file.
                    $this->filesystem->delete($file['basename']);
                }

                return $this->filesystem->rename($v['path'], "$destinationId/$v[name]");
            })
        ) {
            return false;
        }

        // Remove empty write directories.
        return from(array_reverse($fileOrDirectoryIds = self::filespecToDirectoryList($fileOrDirectoryPath)))
            // Skip root directory.
            ->except([reset($fileOrDirectoryIds)])
            // Must skip files otherwise the file we just moved is deleted.
            ->where(function (string $fileOrDirectory) {
                return !self::isFile($this->filesystem->getMetadata($fileOrDirectory));
            })
            ->takeWhile(function (string $directory): bool {
                return !$this->filesystem->listContents($directory);
            })
            ->all(function (string $directory): bool {
                return $this->filesystem->delete($directory);
            })
        ;
    }

    public function delete(string $file): bool
    {
        if (!$filePath = $this->findLeafObject($file, self::WRITE_DIR)) {
            throw new \RuntimeException("Cannot delete file: \"$file\": not found.");
        }

        return $this->filesystem->delete($filePath);
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
        return $this->createDirectoriesArray(self::filespecToDirectoryList($directories));
    }

    private function createDirectoriesArray(array $directories, string $root = null): string
    {
        $directories = array_merge(self::filespecToDirectoryList($root ?? self::WRITE_DIR), $directories);

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

    private function findLeafObject(string $filespec, string $root = null): ?string
    {
        $directories = array_merge(
            self::filespecToDirectoryList($root ?? self::READ_DIR),
            self::filespecToDirectoryList($filespec)
        );

        $parent = '';

        do {
            $directory = array_shift($directories);

            if (!$response = $this->find($directory, $parent)) {
                return null;
            }

            $parent = $response['path'];
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

    private static function filespecToDirectoryList(string $filespec): array
    {
        return explode('/', $filespec);
    }

    private static function isFile(array $file): bool
    {
        return $file['type'] === self::TYPE_FILE;
    }
}
