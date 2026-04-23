<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\PhpFpmManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Logic\Preprocessors\PhpProfiles;

/**
 * Manages PHP-FPM pool configurations.
 *
 * Discovers installed PHP versions, reads/writes pool configs, and detects profiles.
 * All filesystem access goes through FilesystemInterface for testability.
 */
class PhpFpmManager implements PhpFpmManagerInterface
{
    /** @var FilesystemInterface */
    private $fs;

    /** @var string */
    private $confDir;

    /** @var array PHP versions that may be installed on the system. */
    private static $potentialVersions = [
        '5.6',
        '7.0', '7.1', '7.2', '7.3', '7.4',
        '8.0', '8.1', '8.2', '8.3', '8.4', '8.5',
    ];

    /**
     * @param FilesystemInterface $fs
     * @param string $confDir Base PHP configuration directory.
     */
    public function __construct(FilesystemInterface $fs, $confDir = '/etc/php')
    {
        $this->fs = $fs;
        $this->confDir = $confDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstalledVersions()
    {
        $dirs = $this->fs->findDirectories($this->confDir, self::$potentialVersions);

        // Map each directory path to just the version string (directory name)
        $versions = array_map(function ($dir) {
            return basename($dir);
        }, $dirs);

        usort($versions, function ($a, $b) {
            return version_compare($a, $b);
        });

        return $versions;
    }

    /**
     * {@inheritdoc}
     */
    public function versionExists(string $version)
    {
        return in_array($version, $this->getInstalledVersions());
    }

    /**
     * {@inheritdoc}
     */
    public function getConfByDomain(string $domain)
    {
        $configFiles = $this->fs->findByExtension($this->confDir, 'conf');

        // Only consider files inside /fpm/pool.d/ directories
        $configFiles = array_filter($configFiles, function ($file) {
            return strpos($file, '/fpm/pool.d/') !== false;
        });

        foreach ($configFiles as $file) {
            $configFile = basename($file);
            $dirname = dirname($file);

            // Extract PHP version from the path (e.g., /etc/php/8.4/fpm/pool.d/)
            $parts = explode('/', $dirname);
            $phpver = null;
            foreach ($parts as $i => $part) {
                if (in_array($part, self::$potentialVersions)) {
                    $phpver = $part;
                    break;
                }
            }

            // Skip pool files whose PHP version cannot be determined from the
            // path: returning them with phpver=null would poison downstream
            // consumers that expect a non-empty version string.
            if ($phpver === null) {
                continue;
            }

            $contents = $this->fs->getContents($file);
            if ($contents !== false && strpos($contents, "[{$domain}]") !== false) {
                return [
                    'conf' => $file,
                    'name' => $configFile,
                    'phpver' => $phpver,
                    'domain' => $domain,
                ];
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function writeConf(string $domain, string $documentRoot, string $phpVersion, string $userName, string $userGroup, string $profile = 'dev')
    {
        $conf = $this->getConf($domain, $documentRoot, $phpVersion, $userName, $userGroup, $profile);
        $poolDir = "{$this->confDir}/{$phpVersion}/fpm/pool.d";
        $confFile = "{$poolDir}/{$domain}.conf";

        if (!$this->fs->isDir($poolDir)) {
            $this->fs->makeDirectory($poolDir, 0755, true);
        }

        $result = $this->fs->putContents($confFile, $conf);
        if ($result !== false && $this->fs->fileExists($confFile)) {
            return $confFile;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getConf(string $domain, string $documentRoot, string $phpVersion, string $userName, string $userGroup, string $profile = 'dev')
    {
        // Delegate to PhpProfiles if a valid profile is specified
        if (PhpProfiles::isValid($profile)) {
            return PhpProfiles::getFullConfig($profile, $domain, $documentRoot, $phpVersion, $userName, $userGroup);
        }

        // Fallback: basic dev-equivalent configuration
        $conf = <<<CONF
[{$domain}]
user = {$userName}
group = {$userGroup}
listen = /var/run/php/php{$phpVersion}-fpm-{$domain}.sock
listen.owner = {$userName}
listen.group = {$userGroup}
listen.mode = 0660
php_admin_value[disable_functions] = apache_child_terminate,apache_get_modules,apache_getenv,apache_note,apache_setenv
php_admin_flag[allow_url_fopen] = off
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
chdir = /

catch_workers_output = yes
request_terminate_timeout = 180s
slowlog = {$documentRoot}/php{$phpVersion}-fpm-slow.log
php_flag[display_errors] = off
php_admin_value[error_log] = {$documentRoot}/php{$phpVersion}-fpm-errors.log
php_admin_flag[log_errors] = on
php_admin_value[post_max_size] = 128M
php_admin_value[upload_max_filesize] = 128M
php_admin_value[memory_limit] = 1024M
php_value[memory_limit] = 1024M
php_value[short_open_tag] =  On

CONF;

        return $conf;
    }

    /**
     * {@inheritdoc}
     */
    public function detectProfile(string $confFilePath)
    {
        if (!$this->fs->fileExists($confFilePath)) {
            return PhpProfiles::DEV;
        }

        $content = $this->fs->getContents($confFilePath);
        if ($content === false) {
            return PhpProfiles::DEV;
        }

        // The "; Profile: <name>" marker is written by writeConf(). If it's
        // missing or names an unknown profile (hand-edited config), fall back
        // to DEV so downstream consumers always receive a valid profile name.
        if (preg_match('/;\s*Profile:\s*(\w+)/', $content, $matches)) {
            $profile = strtolower($matches[1]);
            if (PhpProfiles::isValid($profile)) {
                return $profile;
            }
        }

        return PhpProfiles::DEV;
    }

    /**
     * {@inheritdoc}
     */
    public function getPotentialVersions()
    {
        return self::$potentialVersions;
    }
}
