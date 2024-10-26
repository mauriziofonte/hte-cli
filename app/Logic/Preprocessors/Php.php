<?php

namespace Mfonte\HteCli\Logic\Preprocessors;

class Php
{
    /**
     * List of "potential" PHP versions that may be installed.
     *
     * @var array
     */
    public static $potentialVersions = ['5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];

    /**
     * Get the PHP configuration directory.
     *
     * @return string
     */
    public static function confDir() : string
    {
        return '/etc/php';
    }
    
    /**
     * Get all installed PHP versions.
     *
     * @return array
     */
    public static function getInstalledVersions() : array
    {
        $errors = [];
        $potentialVersions = dbpat(self::confDir(), self::$potentialVersions, $errors);

        if ($errors) {
            throw new \Exception(implode(PHP_EOL, $errors));
        }

        // map each potential version (that is a dirname) to the directory name itself (that is the PHP version)
        $versions = array_map(function ($version) {
            return basename($version);
        }, $potentialVersions);

        // sort the versions in ascending order
        usort($versions, function ($a, $b) {
            return version_compare($a, $b);
        });

        return $versions;
    }

    /**
     * Check if the given PHP version is installed.
     *
     * @param string $version
     *
     * @return bool
     */
    public static function versionExists(string $version) : bool
    {
        return in_array($version, self::getInstalledVersions());
    }

    /**
     * Get a specific PHP-FPM config for a given domain.
     *
     * @param string $version
     *
     * @return array|null
     */
    public static function getConfByDomain(string $domain) : ?array
    {
        $errors = [];
        $availableConfigFiles = fbext(self::confDir(), 'conf', $errors);

        if ($errors) {
            throw new \Exception(implode(PHP_EOL, $errors));
        }

        // whitelist only available config files that are inside a "/fpm/pool.d/" directory
        $availableConfigFiles = array_filter($availableConfigFiles, function ($file) {
            return strpos($file, '/fpm/pool.d/') !== false;
        });

        foreach ($availableConfigFiles as $file) {
            $configFile = basename($file);
            $dirname = dirname($file);

            // the php version is the 3rd element of the dirname
            $phpver = explode('/', $dirname)[3];

            // read the conf, and, if it contains the literal string [$domain], return
            $contents = file_get_contents($file);
            if (strpos($contents, "[$domain]") !== false) {
                return [
                    'conf' => $file,
                    'name' => $configFile,
                    'phpver' => $phpver,
                    'domain' => $domain
                ];
            }
        }

        return null;
    }

    /**
     * Write a PHP-FPM config for a given domain/documentRoot/phpVersion
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param string $userName
     * @param string $userGroup
     *
     * @return string|null
     */
    public static function writeConf(string $domain, string $documentRoot, string $phpVersion, string $userName, string $userGroup) : ?string
    {
        $php_fpm_conf = self::getConf($domain, $documentRoot, $phpVersion, $userName, $userGroup);

        $confDir = self::confDir();
        $confFile = "{$confDir}/{$phpVersion}/fpm/pool.d/{$domain}.conf";

        if (!is_dir("{$confDir}/{$phpVersion}/fpm/pool.d/")) {
            mkdir("{$confDir}/{$phpVersion}/fpm/pool.d/", 0755, true);
        }

        if (file_put_contents($confFile, $php_fpm_conf) && is_file($confFile)) {
            return $confFile;
        }

        return null;
    }

    /**
     * Get a specific PHP-FPM config for a given domain/documentRoot/phpVersion
     *
     * @param string $version
     *
     * @return array|null
     */
    public static function getConf(string $domain, string $documentRoot, string $phpVersion, string $userName, string $userGroup)
    {
        $php_fpm_conf = <<<CONF
[$domain]
user = $userName
group = $userGroup
listen = /var/run/php/php$phpVersion-fpm-$domain.sock
listen.owner = $userName
listen.group = $userGroup
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
slowlog = $documentRoot/php$phpVersion-fpm-slow.log
php_flag[display_errors] = off
php_admin_value[error_log] = $documentRoot/php$phpVersion-fpm-errors.log
php_admin_flag[log_errors] = on
php_admin_value[post_max_size] = 128M
php_admin_value[upload_max_filesize] = 128M
php_admin_value[memory_limit] = 1024M
php_value[memory_limit] = 1024M
php_value[short_open_tag] =  On

CONF;

        return $php_fpm_conf;
    }
}
