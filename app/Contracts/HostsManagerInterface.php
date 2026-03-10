<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for managing /etc/hosts entries.
 */
interface HostsManagerInterface
{
    /**
     * Marker comment appended to lines managed by HTE-CLI.
     */
    const MARKER = '# hte-cli';

    /**
     * Check if a domain exists in the hosts file.
     *
     * @param string $domain
     * @return bool
     */
    public function exists(string $domain);

    /**
     * Add a domain to the hosts file.
     *
     * @param string $domain
     * @return bool True if added successfully, false if already exists or failed.
     */
    public function add(string $domain);

    /**
     * Remove a domain from the hosts file (only HTE-CLI managed entries).
     *
     * @param string $domain
     * @return bool
     */
    public function remove(string $domain);

    /**
     * List all domains managed by HTE-CLI.
     *
     * @return array
     */
    public function listManagedDomains();

    /**
     * Synchronize the hosts file with the given domain list.
     * Removes all HTE-CLI entries and adds the provided domains.
     *
     * @param array $domains
     * @return bool
     */
    public function sync(array $domains);

    /**
     * Get entry details for a specific domain.
     *
     * @param string $domain
     * @return array|null ['line' => int, 'content' => string, 'managed_by_hte_cli' => bool], or null.
     */
    public function getEntry(string $domain);

    /**
     * Flush the DNS cache (platform-dependent).
     *
     * @return void
     */
    public function flushDnsCache();
}
