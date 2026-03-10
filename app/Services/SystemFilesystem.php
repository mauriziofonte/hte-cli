<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\FilesystemInterface;

/**
 * Real filesystem implementation using PHP's native functions.
 *
 * All methods delegate to standard PHP filesystem functions, making it
 * straightforward to swap with InMemoryFilesystem in tests.
 */
class SystemFilesystem implements FilesystemInterface
{
    /**
     * {@inheritdoc}
     */
    public function fileExists(string $path)
    {
        return is_file($path);
    }

    /**
     * {@inheritdoc}
     */
    public function isDir(string $path)
    {
        return is_dir($path);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(string $path)
    {
        return is_writable($path);
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(string $path)
    {
        return is_readable($path);
    }

    /**
     * {@inheritdoc}
     */
    public function isLink(string $path)
    {
        return is_link($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(string $path)
    {
        return file_get_contents($path);
    }

    /**
     * {@inheritdoc}
     */
    public function putContents(string $path, string $contents, int $flags = 0)
    {
        return file_put_contents($path, $contents, $flags);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path)
    {
        if (!is_file($path) && !is_link($path)) {
            return false;
        }

        return @unlink($path);
    }

    /**
     * {@inheritdoc}
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false)
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to)
    {
        return rename($from, $to);
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link)
    {
        return symlink($target, $link);
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode)
    {
        return chmod($path, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function tempFile(string $dir, string $prefix)
    {
        return tempnam($dir, $prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function removeDirectory(string $path)
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                return @unlink($path);
            }
            return false;
        }

        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        return @rmdir($path);
    }

    /**
     * {@inheritdoc}
     */
    public function findByExtension(string $directory, string $extension, bool $recursive = true)
    {
        $result = [];
        $extension = strtolower(ltrim($extension, '.'));

        try {
            $dirIterator = new \RecursiveDirectoryIterator($directory);
            $iterator = $recursive
                ? new \RecursiveIteratorIterator($dirIterator)
                : new \IteratorIterator($dirIterator);

            foreach ($iterator as $info) {
                if ($info->isFile()) {
                    $fileExt = strtolower(ltrim(pathinfo($info->getFilename(), PATHINFO_EXTENSION), '.'));
                    if ($fileExt === $extension) {
                        $result[] = $info->getPathname();
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently ignore unreadable directories
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findDirectories(string $directory, array $patterns, bool $strict = true, bool $recursive = false)
    {
        $result = [];

        try {
            $dirIterator = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = $recursive
                ? new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST)
                : new \IteratorIterator($dirIterator);

            foreach ($iterator as $info) {
                if ($info->isDir()) {
                    $dirName = $info->getFilename();
                    foreach ($patterns as $pattern) {
                        $match = $strict ? ($dirName === $pattern) : fnmatch($pattern, $dirName);
                        if ($match) {
                            $result[] = $info->getPathname();
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently ignore unreadable directories
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function readLines(string $path, int $flags = 0)
    {
        return file($path, $flags);
    }
}
