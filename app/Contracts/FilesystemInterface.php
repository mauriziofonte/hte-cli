<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Abstraction for filesystem operations.
 *
 * Allows replacing real filesystem access with an in-memory implementation for testing.
 */
interface FilesystemInterface
{
    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path);

    /**
     * Check if a path is a directory.
     *
     * @param string $path
     * @return bool
     */
    public function isDir(string $path);

    /**
     * Check if a path is writable.
     *
     * @param string $path
     * @return bool
     */
    public function isWritable(string $path);

    /**
     * Check if a path is readable.
     *
     * @param string $path
     * @return bool
     */
    public function isReadable(string $path);

    /**
     * Check if a path is a symbolic link.
     *
     * @param string $path
     * @return bool
     */
    public function isLink(string $path);

    /**
     * Read the contents of a file.
     *
     * @param string $path
     * @return string|false
     */
    public function getContents(string $path);

    /**
     * Write contents to a file.
     *
     * @param string $path
     * @param string $contents
     * @param int $flags Optional flags (e.g., FILE_APPEND).
     * @return int|false Number of bytes written, or false on failure.
     */
    public function putContents(string $path, string $contents, int $flags = 0);

    /**
     * Delete a file.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path);

    /**
     * Create a directory (recursively if needed).
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false);

    /**
     * Rename (move) a file or directory.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function rename(string $from, string $to);

    /**
     * Create a symbolic link.
     *
     * @param string $target The target of the symlink.
     * @param string $link The path of the symlink to create.
     * @return bool
     */
    public function symlink(string $target, string $link);

    /**
     * Change file permissions.
     *
     * @param string $path
     * @param int $mode
     * @return bool
     */
    public function chmod(string $path, int $mode);

    /**
     * Create a temporary file and return its path.
     *
     * @param string $dir Directory for the temp file.
     * @param string $prefix Filename prefix.
     * @return string|false
     */
    public function tempFile(string $dir, string $prefix);

    /**
     * Recursively remove a directory and all its contents.
     *
     * @param string $path
     * @return bool
     */
    public function removeDirectory(string $path);

    /**
     * Find all files with a given extension under a directory.
     *
     * @param string $directory
     * @param string $extension File extension without dot (e.g., 'conf').
     * @param bool $recursive Whether to search recursively.
     * @return array List of absolute file paths.
     */
    public function findByExtension(string $directory, string $extension, bool $recursive = true);

    /**
     * Find directories matching the given name patterns.
     *
     * @param string $directory Base directory to search.
     * @param array $patterns Directory names to match.
     * @param bool $strict If true, match exactly; if false, use fnmatch.
     * @param bool $recursive Whether to search recursively.
     * @return array List of absolute directory paths.
     */
    public function findDirectories(string $directory, array $patterns, bool $strict = true, bool $recursive = false);

    /**
     * Read a file and return its lines.
     *
     * @param string $path
     * @param int $flags Optional flags (e.g., FILE_IGNORE_NEW_LINES).
     * @return array|false
     */
    public function readLines(string $path, int $flags = 0);
}
