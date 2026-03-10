<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\HostsManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Contracts\ProcessExecutorInterface;

/**
 * Manages /etc/hosts entries for HTE-CLI VirtualHosts.
 *
 * All entries created by this manager are tagged with the MARKER comment
 * so they can be identified, updated, and removed without affecting
 * manually-added entries.
 */
class HostsManager implements HostsManagerInterface
{
    /** @var FilesystemInterface */
    private $fs;

    /** @var ProcessExecutorInterface */
    private $process;

    /** @var string */
    private $hostsFile;

    /**
     * @param FilesystemInterface $fs
     * @param ProcessExecutorInterface $process
     * @param string $hostsFile Path to the hosts file.
     */
    public function __construct(FilesystemInterface $fs, ProcessExecutorInterface $process, $hostsFile = '/etc/hosts')
    {
        $this->fs = $fs;
        $this->process = $process;
        $this->hostsFile = $hostsFile;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $domain)
    {
        if (!$this->fs->isReadable($this->hostsFile)) {
            return false;
        }

        $contents = $this->fs->getContents($this->hostsFile);
        if ($contents === false) {
            return false;
        }

        $pattern = '/^\s*127\.0\.0\.1\s+.*(?<=\s)' . preg_quote($domain, '/') . '(?=\s|$)/m';

        return preg_match($pattern, $contents) === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $domain)
    {
        if (!$this->fs->isWritable($this->hostsFile)) {
            return false;
        }

        if ($this->exists($domain)) {
            return false;
        }

        $entry = "127.0.0.1    {$domain} www.{$domain} " . self::MARKER;
        $result = $this->fs->putContents($this->hostsFile, "\n{$entry}\n", FILE_APPEND);

        if ($result !== false) {
            $this->flushDnsCache();
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $domain)
    {
        if (!$this->fs->isWritable($this->hostsFile)) {
            return false;
        }

        $lines = $this->fs->readLines($this->hostsFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        $modified = false;
        $newLines = [];
        $pattern = '/(?<=\s)' . preg_quote($domain, '/') . '(?=\s|$)/';

        foreach ($lines as $line) {
            // Only remove lines containing both the domain (exact token match) AND the marker
            if (preg_match($pattern, $line) && strpos($line, self::MARKER) !== false) {
                $modified = true;
                continue;
            }
            $newLines[] = $line;
        }

        if ($modified) {
            $result = $this->fs->putContents($this->hostsFile, implode("\n", $newLines) . "\n");
            if ($result !== false) {
                $this->flushDnsCache();
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function listManagedDomains()
    {
        $domains = [];

        if (!$this->fs->isReadable($this->hostsFile)) {
            return $domains;
        }

        $lines = $this->fs->readLines($this->hostsFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $domains;
        }

        foreach ($lines as $line) {
            if (strpos($line, self::MARKER) !== false) {
                if (preg_match('/127\.0\.0\.1\s+(\S+)/', $line, $matches)) {
                    $domains[] = $matches[1];
                }
            }
        }

        return $domains;
    }

    /**
     * {@inheritdoc}
     */
    public function sync(array $domains)
    {
        if (!$this->fs->isWritable($this->hostsFile)) {
            return false;
        }

        $lines = $this->fs->readLines($this->hostsFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        // Remove all existing HTE-CLI entries
        $newLines = array_values(array_filter($lines, function ($line) {
            return strpos($line, self::MARKER) === false;
        }));

        // Add new entries
        foreach ($domains as $domain) {
            $newLines[] = "127.0.0.1    {$domain} www.{$domain} " . self::MARKER;
        }

        $result = $this->fs->putContents($this->hostsFile, implode("\n", $newLines) . "\n");

        if ($result !== false) {
            $this->flushDnsCache();
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntry(string $domain)
    {
        if (!$this->fs->isReadable($this->hostsFile)) {
            return null;
        }

        $lines = $this->fs->readLines($this->hostsFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        $pattern = '/(?<=\s)' . preg_quote($domain, '/') . '(?=\s|$)/';
        foreach ($lines as $lineNumber => $line) {
            if (preg_match($pattern, $line)) {
                return [
                    'line' => $lineNumber + 1,
                    'content' => $line,
                    'managed_by_hte_cli' => strpos($line, self::MARKER) !== false,
                ];
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function flushDnsCache()
    {
        // macOS
        if (PHP_OS === 'Darwin') {
            @$this->process->execute('dscacheutil -flushcache 2>/dev/null');
            @$this->process->execute('sudo killall -HUP mDNSResponder 2>/dev/null');
        }

        // Linux with systemd-resolved
        if (PHP_OS === 'Linux') {
            @$this->process->execute('systemd-resolve --flush-caches 2>/dev/null');
            @$this->process->execute('nscd -i hosts 2>/dev/null');
        }
    }
}
