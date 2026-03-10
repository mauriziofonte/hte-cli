<?php

namespace Mfonte\HteCli\Logic\Preprocessors;

class PhpProfiles
{
    /**
     * Development profile - minimal restrictions, suitable for local dev.
     */
    const DEV = 'dev';

    /**
     * Staging profile - moderate restrictions, balances security and functionality.
     */
    const STAGING = 'staging';

    /**
     * Hardened profile - maximum restrictions, may break some applications.
     * Warning: This profile disables functions used by Artisan, Composer, and PHPUnit.
     */
    const HARDENED = 'hardened';

    /**
     * Base disabled functions (common across all profiles).
     *
     * @var array
     */
    private static $baseDisabledFunctions = [
        'apache_child_terminate',
        'apache_get_modules',
        'apache_getenv',
        'apache_note',
        'apache_setenv',
    ];

    /**
     * Additional functions disabled in staging profile.
     *
     * @var array
     */
    private static $stagingDisabledFunctions = [
        'dl',
        'highlight_file',
        'show_source',
        'phpinfo',
    ];

    /**
     * Additional functions disabled in hardened profile.
     * WARNING: These will break Artisan, Composer, PHPUnit, and many frameworks.
     *
     * @var array
     */
    private static $hardenedDisabledFunctions = [
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'popen',
        'proc_open',
        'proc_close',
        'proc_get_status',
        'proc_terminate',
        'pcntl_exec',
        'pcntl_fork',
        'pcntl_signal',
        'symlink',
    ];

    /**
     * Get the list of disabled functions for a profile.
     *
     * @param string $profile
     * @return string Comma-separated list of disabled functions
     */
    public static function getDisabledFunctions(string $profile): string
    {
        $functions = self::$baseDisabledFunctions;

        if ($profile === self::STAGING || $profile === self::HARDENED) {
            $functions = array_merge($functions, self::$stagingDisabledFunctions);
        }

        if ($profile === self::HARDENED) {
            $functions = array_merge($functions, self::$hardenedDisabledFunctions);
        }

        return implode(',', array_unique($functions));
    }

    /**
     * Get additional PHP configuration for a profile.
     *
     * @param string $profile
     * @param string $documentRoot
     * @return array Key-value pairs of PHP settings
     */
    public static function getAdditionalConfig(string $profile, string $documentRoot): array
    {
        $config = [];

        // Staging and hardened profiles get additional security settings
        if ($profile === self::STAGING || $profile === self::HARDENED) {
            $config['open_basedir'] = "{$documentRoot}:/var/lib/php:/usr/lib/php:/usr/share/php:/tmp:/dev/urandom";
            $config['allow_url_include'] = 'off';
            $config['session.cookie_httponly'] = 'on';
            $config['session.use_strict_mode'] = 'on';
            $config['session.use_only_cookies'] = 'on';
        }

        // Hardened profile gets even more restrictions
        if ($profile === self::HARDENED) {
            $config['auto_prepend_file'] = '';
            $config['auto_append_file'] = '';
            $config['expose_php'] = 'off';
            // Reduced limits for hardened profile
            $config['memory_limit'] = '256M';
            $config['post_max_size'] = '32M';
            $config['upload_max_filesize'] = '32M';
            $config['max_execution_time'] = '30';
            $config['max_input_time'] = '60';
        }

        return $config;
    }

    /**
     * Get process manager settings for a profile.
     *
     * @param string $profile
     * @return array Key-value pairs of PM settings
     */
    public static function getProcessManagerConfig(string $profile): array
    {
        // Default settings suitable for development
        $config = [
            'pm' => 'dynamic',
            'pm.max_children' => 5,
            'pm.start_servers' => 2,
            'pm.min_spare_servers' => 1,
            'pm.max_spare_servers' => 3,
        ];

        // Add max_requests to prevent memory leaks
        if ($profile === self::STAGING || $profile === self::HARDENED) {
            $config['pm.max_requests'] = 500;
        }

        return $config;
    }

    /**
     * Get the full configuration block for a profile.
     *
     * @param string $profile
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param string $userName
     * @param string $userGroup
     * @return string Complete PHP-FPM pool configuration
     */
    public static function getFullConfig(
        string $profile,
        string $domain,
        string $documentRoot,
        string $phpVersion,
        string $userName,
        string $userGroup
    ): string {
        $disabledFunctions = self::getDisabledFunctions($profile);
        $additionalConfig = self::getAdditionalConfig($profile, $documentRoot);
        $pmConfig = self::getProcessManagerConfig($profile);

        // Default resource limits (can be overridden by profile)
        $memoryLimit = $additionalConfig['memory_limit'] ?? '1024M';
        $postMaxSize = $additionalConfig['post_max_size'] ?? '128M';
        $uploadMaxSize = $additionalConfig['upload_max_filesize'] ?? '128M';

        $config = <<<CONF
[{$domain}]
; Profile: {$profile}
user = {$userName}
group = {$userGroup}
listen = /var/run/php/php{$phpVersion}-fpm-{$domain}.sock
listen.owner = {$userName}
listen.group = {$userGroup}
listen.mode = 0660

; Security: Disabled functions
php_admin_value[disable_functions] = {$disabledFunctions}

; Security: URL handling
php_admin_flag[allow_url_fopen] = off

; Process manager
pm = {$pmConfig['pm']}
pm.max_children = {$pmConfig['pm.max_children']}
pm.start_servers = {$pmConfig['pm.start_servers']}
pm.min_spare_servers = {$pmConfig['pm.min_spare_servers']}
pm.max_spare_servers = {$pmConfig['pm.max_spare_servers']}
CONF;

        // Add pm.max_requests if set
        if (isset($pmConfig['pm.max_requests'])) {
            $config .= "\npm.max_requests = {$pmConfig['pm.max_requests']}";
        }

        $config .= <<<CONF

chdir = /

; Logging
catch_workers_output = yes
request_terminate_timeout = 180s
slowlog = {$documentRoot}/php{$phpVersion}-fpm-slow.log
php_flag[display_errors] = off
php_admin_value[error_log] = {$documentRoot}/php{$phpVersion}-fpm-errors.log
php_admin_flag[log_errors] = on

; Resource limits
php_admin_value[post_max_size] = {$postMaxSize}
php_admin_value[upload_max_filesize] = {$uploadMaxSize}
php_admin_value[memory_limit] = {$memoryLimit}
php_value[memory_limit] = {$memoryLimit}

; PHP settings
php_value[short_open_tag] = On

CONF;

        // Add profile-specific additional settings
        foreach ($additionalConfig as $key => $value) {
            // Skip already handled settings
            if (in_array($key, ['memory_limit', 'post_max_size', 'upload_max_filesize'])) {
                continue;
            }

            if (in_array($value, ['on', 'off'])) {
                $config .= "php_admin_flag[{$key}] = {$value}\n";
            } elseif ($value === '') {
                $config .= "php_admin_value[{$key}] = \"\"\n";
            } else {
                $config .= "php_admin_value[{$key}] = {$value}\n";
            }
        }

        return $config;
    }

    /**
     * Check if a profile name is valid.
     *
     * @param string $profile
     * @return bool
     */
    public static function isValid(string $profile): bool
    {
        return in_array($profile, [self::DEV, self::STAGING, self::HARDENED]);
    }

    /**
     * Get all available profile names.
     *
     * @return array
     */
    public static function getAll(): array
    {
        return [self::DEV, self::STAGING, self::HARDENED];
    }

    /**
     * Get description for a profile.
     *
     * @param string $profile
     * @return string
     */
    public static function getDescription(string $profile): string
    {
        $descriptions = [
            self::DEV => 'Development - Minimal restrictions, high memory limits',
            self::STAGING => 'Staging - Moderate security, open_basedir enabled, session hardening',
            self::HARDENED => 'Hardened - Maximum security (WARNING: may break Laravel/Composer/PHPUnit)',
        ];

        return $descriptions[$profile] ?? 'Unknown profile';
    }
}
