<?php

namespace Tests\Doubles;

use Mfonte\HteCli\Contracts\FilesystemInterface;

/**
 * In-memory filesystem for testing.
 *
 * Stores files and directories in associative arrays, eliminating the need
 * for real filesystem access in unit tests. All paths are normalized to
 * use forward slashes.
 */
class InMemoryFilesystem implements FilesystemInterface
{
    /** @var array Associative array of path => content for files. */
    private $files = [];

    /** @var array Associative array of path => true for directories. */
    private $dirs = [];

    /** @var array Associative array of link => target for symlinks. */
    private $links = [];

    /** @var array Associative array of path => mode for permissions. */
    private $permissions = [];

    /** @var array Paths marked as unwritable for testing error paths. */
    private $unwritable = [];

    /** @var array Paths marked as unreadable for testing error paths. */
    private $unreadable = [];

    /** @var int Counter for temp file uniqueness. */
    private $tempCounter = 0;

    /**
     * Pre-populate the filesystem with files and directories.
     *
     * @param array $files Associative array of path => content.
     * @param array $dirs List of directory paths.
     */
    public function __construct(array $files = [], array $dirs = [])
    {
        foreach ($dirs as $dir) {
            $this->makeDirectory($dir, 0755, true);
        }
        foreach ($files as $path => $content) {
            $this->putContents($path, $content);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fileExists(string $path)
    {
        return isset($this->files[$path]) || isset($this->links[$path]);
    }

    /**
     * {@inheritdoc}
     */
    public function isDir(string $path)
    {
        return isset($this->dirs[$path]);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(string $path)
    {
        if (isset($this->unwritable[$path])) {
            return false;
        }
        return $this->fileExists($path) || $this->isDir($path) || $this->isDir(dirname($path));
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(string $path)
    {
        if (isset($this->unreadable[$path])) {
            return false;
        }
        return $this->fileExists($path) || $this->isDir($path);
    }

    /**
     * {@inheritdoc}
     */
    public function isLink(string $path)
    {
        return isset($this->links[$path]);
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(string $path)
    {
        if (isset($this->files[$path])) {
            return $this->files[$path];
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function putContents(string $path, string $contents, int $flags = 0)
    {
        // Ensure parent directory exists
        $dir = dirname($path);
        if ($dir !== '.' && !$this->isDir($dir)) {
            $this->makeDirectory($dir, 0755, true);
        }

        if ($flags & FILE_APPEND) {
            $existing = isset($this->files[$path]) ? $this->files[$path] : '';
            $this->files[$path] = $existing . $contents;
        } else {
            $this->files[$path] = $contents;
        }

        return strlen($contents);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path)
    {
        if (isset($this->links[$path])) {
            unset($this->links[$path]);
            return true;
        }

        if (isset($this->files[$path])) {
            unset($this->files[$path]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false)
    {
        if ($this->isDir($path)) {
            return true;
        }

        if ($recursive) {
            // Create all parent directories
            $parts = explode('/', ltrim($path, '/'));
            $current = '';
            foreach ($parts as $part) {
                $current .= '/' . $part;
                $this->dirs[$current] = true;
            }
        } else {
            $this->dirs[$path] = true;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to)
    {
        if (isset($this->files[$from])) {
            $this->files[$to] = $this->files[$from];
            unset($this->files[$from]);
            return true;
        }

        if (isset($this->dirs[$from])) {
            // Rename directory and all contents under it
            $this->dirs[$to] = true;
            unset($this->dirs[$from]);

            // Move files under the old directory
            foreach ($this->files as $filePath => $content) {
                if (strpos($filePath, $from . '/') === 0) {
                    $newFilePath = $to . substr($filePath, strlen($from));
                    $this->files[$newFilePath] = $content;
                    unset($this->files[$filePath]);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link)
    {
        $this->links[$link] = $target;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode)
    {
        $this->permissions[$path] = $mode;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function tempFile(string $dir, string $prefix)
    {
        $this->tempCounter++;
        $path = rtrim($dir, '/') . '/' . $prefix . $this->tempCounter;
        $this->files[$path] = '';
        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function removeDirectory(string $path)
    {
        // Remove all files under the directory
        foreach ($this->files as $filePath => $content) {
            if ($filePath === $path || strpos($filePath, $path . '/') === 0) {
                unset($this->files[$filePath]);
            }
        }

        // Remove the directory and all subdirectories
        foreach ($this->dirs as $dirPath => $val) {
            if ($dirPath === $path || strpos($dirPath, $path . '/') === 0) {
                unset($this->dirs[$dirPath]);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function findByExtension(string $directory, string $extension, bool $recursive = true)
    {
        $result = [];
        $extension = strtolower(ltrim($extension, '.'));
        $prefix = rtrim($directory, '/') . '/';

        foreach ($this->files as $path => $content) {
            if (strpos($path, $prefix) !== 0) {
                continue;
            }

            // If non-recursive, skip files in subdirectories
            if (!$recursive) {
                $relative = substr($path, strlen($prefix));
                if (strpos($relative, '/') !== false) {
                    continue;
                }
            }

            $fileExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($fileExt === $extension) {
                $result[] = $path;
            }
        }

        sort($result);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findDirectories(string $directory, array $patterns, bool $strict = true, bool $recursive = false)
    {
        $result = [];
        $prefix = rtrim($directory, '/') . '/';

        foreach ($this->dirs as $dirPath => $val) {
            if (strpos($dirPath, $prefix) !== 0) {
                continue;
            }

            $relative = substr($dirPath, strlen($prefix));

            // If non-recursive, skip nested directories
            if (!$recursive && strpos($relative, '/') !== false) {
                continue;
            }

            $dirName = basename($dirPath);
            foreach ($patterns as $pattern) {
                $match = $strict ? ($dirName === $pattern) : fnmatch($pattern, $dirName);
                if ($match) {
                    $result[] = $dirPath;
                    break;
                }
            }
        }

        sort($result);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function readLines(string $path, int $flags = 0)
    {
        if (!isset($this->files[$path])) {
            return false;
        }

        $content = $this->files[$path];
        $lines = explode("\n", $content);

        // Emulate FILE_IGNORE_NEW_LINES behavior (default explode already strips them)
        // If FILE_IGNORE_NEW_LINES is NOT set, add newlines back
        if (!($flags & FILE_IGNORE_NEW_LINES)) {
            $lines = array_map(function ($line) {
                return $line . "\n";
            }, $lines);
            // The last element should not have a trailing newline if content doesn't end with one
            if (substr($content, -1) !== "\n") {
                $last = count($lines) - 1;
                $lines[$last] = rtrim($lines[$last], "\n");
            }
        }

        return $lines;
    }

    /**
     * Get all stored files (for test assertions).
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Get all stored directories (for test assertions).
     *
     * @return array
     */
    public function getDirs()
    {
        return $this->dirs;
    }

    /**
     * Get all stored symlinks (for test assertions).
     *
     * @return array
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Mark a path as unwritable (for testing error paths).
     *
     * @param string $path
     * @return void
     */
    public function setUnwritable(string $path)
    {
        $this->unwritable[$path] = true;
    }

    /**
     * Mark a path as unreadable (for testing error paths).
     *
     * @param string $path
     * @return void
     */
    public function setUnreadable(string $path)
    {
        $this->unreadable[$path] = true;
    }
}
