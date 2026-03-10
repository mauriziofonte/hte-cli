<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\ServiceManagerInterface;
use Mfonte\HteCli\Contracts\ProcessExecutorInterface;

/**
 * Manages Apache and PHP-FPM system services via systemctl and a2ensite/a2dissite.
 */
class ServiceManager implements ServiceManagerInterface
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
    public function restartApache()
    {
        return $this->process->execute('systemctl restart apache2.service');
    }

    /**
     * {@inheritdoc}
     */
    public function restartPhpFpm(string $phpVersion)
    {
        $safe = escapeshellarg("php{$phpVersion}-fpm.service");
        return $this->process->execute("systemctl restart {$safe}");
    }

    /**
     * {@inheritdoc}
     */
    public function enableSite(string $configName)
    {
        $safe = escapeshellarg($configName);
        return $this->process->execute("a2ensite {$safe}");
    }

    /**
     * {@inheritdoc}
     */
    public function disableSite(string $configName)
    {
        $safe = escapeshellarg($configName);
        return $this->process->execute("a2dissite {$safe} 2>/dev/null");
    }
}
