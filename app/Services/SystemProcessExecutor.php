<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\ProcessExecutorInterface;

/**
 * Executes shell commands via proc_open.
 *
 * Production implementation of ProcessExecutorInterface.
 */
class SystemProcessExecutor implements ProcessExecutorInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(string $command)
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

        // Wait for the process to complete, yielding CPU between checks
        $status = proc_get_status($process);
        while ($status['running']) {
            usleep(10000); // 10ms
            $status = proc_get_status($process);
        }

        $stdOutput = stream_get_contents($pipes[1]);
        $stdError = stream_get_contents($pipes[2]);

        proc_close($process);

        return [intval($status['exitcode']), trim($stdOutput), trim($stdError)];
    }
}
