<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for managing system services (Apache, PHP-FPM).
 */
interface ServiceManagerInterface
{
    /**
     * Restart the Apache2 service.
     *
     * @return array{int, string, string} [exitCode, stdout, stderr]
     */
    public function restartApache();

    /**
     * Restart a PHP-FPM service for a specific version.
     *
     * @param string $phpVersion e.g., '8.4'.
     * @return array{int, string, string} [exitCode, stdout, stderr]
     */
    public function restartPhpFpm(string $phpVersion);

    /**
     * Enable an Apache site by config name.
     *
     * @param string $configName Config name without .conf extension.
     * @return array{int, string, string} [exitCode, stdout, stderr]
     */
    public function enableSite(string $configName);

    /**
     * Disable an Apache site by config name.
     *
     * @param string $configName Config name without .conf extension.
     * @return array{int, string, string} [exitCode, stdout, stderr]
     */
    public function disableSite(string $configName);
}
