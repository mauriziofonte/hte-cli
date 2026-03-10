<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\EnvironmentCheckerInterface;
use Mfonte\HteCli\Contracts\ProcessExecutorInterface;

/**
 * Checks that the runtime environment meets HTE-CLI requirements.
 *
 * Verifies OS compatibility, required binaries, and PHP functions.
 */
class EnvironmentChecker implements EnvironmentCheckerInterface
{
    /** @var ProcessExecutorInterface */
    private $process;

    /**
     * @param ProcessExecutorInterface $process
     */
    public function __construct(ProcessExecutorInterface $process)
    {
        $this->process = $process;
    }

    /**
     * {@inheritdoc}
     */
    public function isSupportedOs()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN';
    }

    /**
     * {@inheritdoc}
     */
    public function hasShell()
    {
        return array_key_exists('SHELL', $_SERVER);
    }

    /**
     * {@inheritdoc}
     */
    public function hasBinary(string $binary)
    {
        list($exitCode, $output, $error) = $this->process->execute('command -v ' . escapeshellarg($binary));
        return $exitCode === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function hasFunction(string $function)
    {
        return function_exists($function);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredFunctions()
    {
        return [
            'exec',
            'posix_getuid',
            'posix_getpwuid',
            'posix_getgrgid',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredBinaries()
    {
        return ['apache2', 'php'];
    }
}
