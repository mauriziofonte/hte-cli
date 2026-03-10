<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for managing PHP-FPM pool configurations.
 */
interface PhpFpmManagerInterface
{
    /**
     * Get all PHP versions installed on the system.
     *
     * @return array Sorted list of version strings (e.g., ['7.4', '8.0', '8.4']).
     * @throws \Exception If the PHP config directory cannot be read.
     */
    public function getInstalledVersions();

    /**
     * Check if a specific PHP version is installed.
     *
     * @param string $version
     * @return bool
     */
    public function versionExists(string $version);

    /**
     * Find the PHP-FPM pool configuration for a given domain.
     *
     * @param string $domain
     * @return array|null ['conf' => path, 'name' => filename, 'phpver' => version, 'domain' => domain], or null.
     */
    public function getConfByDomain(string $domain);

    /**
     * Write a PHP-FPM pool configuration to disk.
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param string $userName
     * @param string $userGroup
     * @param string $profile Hardening profile (dev, staging, hardened).
     * @return string|null Path to the created config file, or null on failure.
     */
    public function writeConf(string $domain, string $documentRoot, string $phpVersion, string $userName, string $userGroup, string $profile = 'dev');

    /**
     * Generate PHP-FPM pool configuration content (does not write to disk).
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param string $userName
     * @param string $userGroup
     * @param string $profile
     * @return string
     */
    public function getConf(string $domain, string $documentRoot, string $phpVersion, string $userName, string $userGroup, string $profile = 'dev');

    /**
     * Detect the hardening profile from an existing PHP-FPM config file.
     *
     * @param string $confFilePath Absolute path to the PHP-FPM pool config.
     * @return string The detected profile name, defaults to 'dev'.
     */
    public function detectProfile(string $confFilePath);

    /**
     * Get the list of potential PHP versions that may be installed.
     *
     * @return array
     */
    public function getPotentialVersions();
}
