<?php

if (! function_exists('rrmdir')) {
    /**
     * Recursive Remove Directory function
     */
    function rrmdir(string $path): bool
    {
        if (is_dir($path)) {
            array_map("rrmdir", glob($path . DIRECTORY_SEPARATOR . '{,.[!.]}*', GLOB_BRACE));
            @rmdir($path);

            return true;
        } elseif (is_file($path)) {
            @unlink($path);

            return true;
        }

        return false;
    }
}

if (! function_exists('fbext')) {
    /**
     * FindByExtension function
     * Find all files with a specific extension in a directory
     *
     * @param string $dirname
     * @param string $extension
     * @param array &$error
     * @param bool $recursive
     *
     * @return array
     */
    function fbext(string $dirname, string $extension, ?array &$errors = null, bool $recursive = true) : array
    {
        $result = [];

        try {
            $directory = new RecursiveDirectoryIterator($dirname);
            $iterator = $recursive ? new RecursiveIteratorIterator($directory) : new IteratorIterator($directory);
        
            foreach ($iterator as $info) {
                if ($info->isFile()) {
                    $fileExtension = strtolower(trim(pathinfo($info->getFilename(), PATHINFO_EXTENSION), '.'));
                    if (strtolower(trim($extension, '.')) === $fileExtension) {
                        $result[] = $info->getPathname();
                    }
                }
            }
        } catch(\UnexpectedValueException $e) {
            if ($errors !== null) {
                $errors[] = "Unreadable directory: {$e->getMessage()}";
            }
        } catch(\RuntimeException $e) {
            if ($errors !== null) {
                $errors[] = "Failed to open directory: {$e->getMessage()}";
            }
        } catch (\OutOfBoundsException $e) {
            if ($errors !== null) {
                $errors[] = "Out of bounds access: {$e->getMessage()}";
            }
        } catch (\Exception $e) {
            if ($errors !== null) {
                $errors[] = "Unexpected error: {$e->getMessage()}";
            }
        }
    
        return $result;
    }
}

if (! function_exists('dbpat')) {
    /**
     * DirectoryByPattern function
     * Find all directories matching a pattern in a directory
     *
     * @param string $dirname
     * @param array $patterns
     * @param bool $strict
     * @param bool $recursive
     *
     * @return array
     */
    function dbpat(string $dirname, array $patterns, ?array &$errors = null, bool $strict = true, bool $recursive = false) : array
    {
        $result = [];

        try {
            $dirIterator = new RecursiveDirectoryIterator($dirname, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = $recursive ? new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST) : new IteratorIterator($dirIterator);
                    
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
        } catch(\UnexpectedValueException $e) {
            if ($errors !== null) {
                $errors[] = "Unreadable directory: {$e->getMessage()}";
            }
        } catch(\RuntimeException $e) {
            if ($errors !== null) {
                $errors[] = "Failed to open directory: {$e->getMessage()}";
            }
        } catch (\OutOfBoundsException $e) {
            if ($errors !== null) {
                $errors[] = "Out of bounds access: {$e->getMessage()}";
            }
        } catch (\Exception $e) {
            if ($errors !== null) {
                $errors[] = "Unexpected error: {$e->getMessage()}";
            }
        }

        return $result;
    }
}

if (!function_exists('validate_domain')) {
    /**
     * Validate a domain name and returns the lowerercase version of it. Returns null upon failure.
     *
     * @param string $domain
     *
     * @return ?string
     */
    function validate_domain(string $domain): ?string
    {
        $valid = preg_match('/^(?!-)([a-z0-9-]*[a-z0-9]+(\.[a-z0-9-]*[a-z0-9]+)*\.[a-z]{2,})$/i', $domain) === 1 || filter_var($domain, FILTER_VALIDATE_IP);

        return $valid ? mb_strtolower($domain) : null;
    }
}

if (!function_exists('answer_to_bool')) {
    /**
     * Converts a User Answer to a boolean value.
     *
     * @param mixed $value
     *
     * @return bool|null - A boolean if the value can be converted, null otherwise.
     */
    function answer_to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['1', 'y', 'yes', 'true'])) {
                return true;
            }
        }
        
        if (is_int($value)) {
            if (intval($value) === 1) {
                return true;
            } else {
                return false;
            }
        }

        return null;
    }
}

if (!function_exists('proc_exec')) {
    /**
     * Executes a shell command, captures output, errors, and returns the exit code.
     * ATTENTION: the sanitization of the command is up to the caller. This function does not escape the command.
     *
     * This function utilizes `proc_open` to execute a shell command, allowing full access
     * to standard input, output, and error streams. It waits for the process to complete
     * and returns the exit code, standard output, and standard error.
     *
     * @param string $command The shell command to execute.
     *
     * @throws \RuntimeException If the process could not be created.
     *
     * @return array{int, string, string} An array containing:
     *     - int: The exit code of the command.
     *     - string: The standard output (stdout) of the command.
     *     - string: The standard error (stderr) of the command.
     */
    function proc_exec(string $command) : array
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('The proc_open function is not available on this system.');
        }
        
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not create a valid process');
        }

        // This will prevent to program from continuing until the processes is complete
        // Note: exitcode is created on the final loop here
        $status = proc_get_status($process);
        while ($status['running']) {
            $status = proc_get_status($process);
        }

        $stdOutput = stream_get_contents($pipes[1]);
        $stdError  = stream_get_contents($pipes[2]);

        proc_close($process);

        return [intval($status['exitcode']), trim($stdOutput), trim($stdError)];
    }
}
