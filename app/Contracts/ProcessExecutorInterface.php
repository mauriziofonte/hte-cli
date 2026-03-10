<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Abstraction for executing shell commands.
 *
 * Allows swapping the real proc_open implementation with a fake in tests.
 */
interface ProcessExecutorInterface
{
    /**
     * Execute a shell command and return the result.
     *
     * @param string $command The shell command to execute.
     * @return array{int, string, string} [exitCode, stdout, stderr]
     * @throws \RuntimeException If the process could not be created.
     */
    public function execute(string $command);
}
