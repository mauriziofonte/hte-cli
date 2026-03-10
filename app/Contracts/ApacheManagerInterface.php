<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for managing Apache VirtualHost configurations.
 */
interface ApacheManagerInterface
{
    /**
     * Get a list of all HTE-CLI managed VirtualHosts.
     *
     * @return array Each element contains: index, conf, name, phpver, domain, docroot, enabled, ssl, forcedssl, sslcertfile, sslkeyfile.
     * @throws \Exception If the sites-available directory cannot be read.
     */
    public function getVhostsList();

    /**
     * Find a VirtualHost configuration by domain name.
     *
     * @param string $domain
     * @return array|null The VirtualHost info array, or null if not found.
     */
    public function getConfByDomain(string $domain);

    /**
     * Get the next available config file index (zero-padded to 3 digits).
     *
     * @return string e.g., '001', '002', '015'.
     */
    public function getConfMaxIndex();

    /**
     * Create a new VirtualHost configuration file on disk.
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param bool $enableHttps
     * @param bool $forceHttps
     * @return string|null Path to the created config file, or null on failure.
     */
    public function createVhost(string $domain, string $documentRoot, string $phpVersion, bool $enableHttps = true, bool $forceHttps = true);

    /**
     * Generate Apache VirtualHost configuration content (does not write to disk).
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param bool $enableHttps
     * @param bool $forceHttps
     * @return string The generated Apache configuration.
     */
    public function getConf(string $domain, string $documentRoot, string $phpVersion, bool $enableHttps = true, bool $forceHttps = true);

    /**
     * Renumber VirtualHost config files sequentially starting from 001.
     *
     * @return array ['renamed' => int, 'errors' => string[]]
     */
    public function normalizeIndexes();

    /**
     * Check if a config file needs the localhost HTTPS bypass fix.
     *
     * @param string $confFile Absolute path to the Apache config file.
     * @return bool
     */
    public function needsLocalhostBypassFix(string $confFile);

    /**
     * Apply the localhost HTTPS bypass fix to a config file.
     *
     * @param string $confFile Absolute path to the Apache config file.
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function fixLocalhostBypass(string $confFile);

    /**
     * Check if a document root contains an .htaccess with HTTPS redirect rules.
     *
     * @param string $docroot Path to the document root.
     * @return array ['has_redirect' => bool, 'file' => string|null, 'line' => int|null]
     */
    public function hasHtaccessHttpsRedirect(string $docroot);
}
