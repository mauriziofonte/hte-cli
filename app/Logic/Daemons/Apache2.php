<?php

namespace Mfonte\HteCli\Logic\Daemons;

use Carbon\Carbon;

class Apache2
{
    /**
     * Get the Apache2 "Available VirtualHosts" configuration directory.
     *
     * @return string
     */
    public static function sitesAvailableDir() : string
    {
        return '/etc/apache2/sites-available';
    }

    /**
     * Get the Apache2 "Enabled VirtualHosts" configuration directory.
     *
     * @return string
     */
    public static function sitesEnabledDir() : string
    {
        return '/etc/apache2/sites-enabled';
    }

    /**
     * Return all informations about the Apache2 VirtualHosts.
     *
     * @return array
     */
    public static function getVhostsList() : array
    {
        $virtualHosts = [];

        $errors = [];
        $availableConfigFiles = fbext(self::sitesAvailableDir(), 'conf', $errors);

        if ($errors) {
            throw new \Exception(implode(PHP_EOL, $errors));
        }

        foreach ($availableConfigFiles as $file) {

            // get the filename only
            $configFile = basename($file);

            // read the file, and, if it contains the literal string "# Auto-generated by the test environment creator", then it's a test environment
            $contents = file_get_contents($file);
            if (strpos($contents, '# Auto-generated by the test environment creator') !== false) {
                // check the PHP-FPM version used, by matching the string "SetHandler "proxy:unix:/var/run/php/php$phpver-fpm-$domain.sock|" in the file
                $phpver = $domain = null;
                if (preg_match('/SetHandler "proxy:unix:\/var\/run\/php\/php([0-9\.]+)-fpm-([^"\|]+)/', $contents, $matches)) {
                    $phpver = $matches[1];
                    $domain = str_replace('.sock', '', $matches[2]);
                }

                // check if this VirtualHosts has SSL configured, by matching <VirtualHost 127.0.0.1:443>
                $ssl = false;
                $sslCertFile = $sslKeyFile = null;
                if (preg_match('/<VirtualHost 127\.0\.0\.1:443>/', $contents)) {
                    $ssl = true;
                    // match SSLCertificateFile "/etc/apache2/certs-selfsigned/$domain.crt"
                    if (preg_match('/SSLCertificateFile "([^"]+)"/', $contents, $matches)) {
                        $sslCertFile = $matches[1];
                    }
                    // match SSLCertificateKeyFile "/etc/apache2/certs-selfsigned/$domain.key"
                    if (preg_match('/SSLCertificateKeyFile "([^"]+)"/', $contents, $matches)) {
                        $sslKeyFile = $matches[1];
                    }
                }

                // see if HTTPS is forced by checking if the file contains the string "# Auto-generated Force HTTPS"
                $forceSsl = false;
                if (strpos($contents, '# Auto-generated Force HTTPS') !== false) {
                    $forceSsl = true;
                }

                // see if this VirtualHost is enabled by checking if it's symlinked in the sites-enabled directory
                $enabled = false;
                if (is_file(self::sitesEnabledDir() . "/{$configFile}")) {
                    $enabled = true;
                }

                // split the "index" part from the filename, if the filename matches 002-domain.conf
                $index = 0;
                if (preg_match('/^([0-9]+)-(.*)\.conf$/ui', $configFile, $matches)) {
                    $index = intval($matches[1]);
                }

                // get the document root
                $documentRoot = null;
                if (preg_match('/DocumentRoot ([^\n]+)/', $contents, $matches)) {
                    $documentRoot = $matches[1];
                }

                $virtualHosts[] = [
                    'index' => $index,
                    'conf' => $file,
                    'name' => $configFile,
                    'phpver' => $phpver,
                    'domain' => $domain,
                    'docroot' => $documentRoot,
                    'enabled' => $enabled,
                    'ssl' => $ssl,
                    'forcedssl' => $forceSsl,
                    'sslcertfile' => $sslCertFile,
                    'sslkeyfile' => $sslKeyFile
                ];
            }
        }

        // sort the VirtualHosts by index
        usort($virtualHosts, function ($a, $b) {
            return $a['index'] <=> $b['index'];
        });

        return $virtualHosts;
    }

    /**
     * Check if the given domain exists in the Apache2 VirtualHosts.
     *
     * @param string $domain
     *
     * @return array|null
     */
    public static function getConfByDomain(string $domain) : ?array
    {
        $status = self::getVhostsList();

        // search for the domain in the VirtualHosts
        $vhost = array_filter($status, function ($vhost) use ($domain) {
            return $vhost['domain'] == $domain;
        });

        // if the domain exists, return the first element of the array
        if (count($vhost) > 0) {
            return array_shift($vhost);
        }

        return null;
    }

    /**
     * Get the Max Index of configured Apache2 VirtualHosts.
     *
     * @return string
     */
    public static function getConfMaxIndex() : string
    {
        $errors = [];
        $files = fbext(self::sitesAvailableDir(), 'conf');
        if ($errors) {
            throw new \Exception(implode(PHP_EOL, $errors));
        }

        if ($files && count($files) > 0) {
            $max_index = 0;
            foreach ($files as $file) {
                $file = basename($file);
                if (preg_match('/^([0-9]+).*\.conf$/ui', $file, $matches)) {
                    $index = intval($matches[1]);
                    if ($index >= $max_index) {
                        $max_index = $index;
                    }
                }
            }
    
            return str_pad($max_index + 1, 3, '0', STR_PAD_LEFT);
        }
    
        return '001';
    }

    /**
     * Create a new Apache2 VirtualHost.
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param bool $enableHttps
     * @param bool $forceHttps
     *
     * @return string|null
     */
    public static function createVhost(string $domain, string $documentRoot, string $phpVersion, bool $enableHttps = true, bool $forceHttps = true) : ?string
    {
        $conf = self::getConf($domain, $documentRoot, $phpVersion, $enableHttps, $forceHttps);
        $max_index = self::getConfMaxIndex();
        $confFile = self::sitesAvailableDir() . "/{$max_index}-{$domain}.conf";

        // write the conf file
        if (file_put_contents($confFile, $conf) && is_file($confFile)) {
            return $confFile;
        }

        return null;
    }

    /**
     * Get the Apache2 VirtualHost configuration for a given domain/documentRoot/phpVersion
     *
     * @param string $domain
     * @param string $documentRoot
     * @param string $phpVersion
     * @param bool $enableHttps
     * @param bool $forceHttps
     *
     * @return string
     */
    public static function getConf(string $domain, string $documentRoot, string $phpVersion, bool $enableHttps = true, bool $forceHttps = true) : string
    {
        $newline = "\\n";
        $date = Carbon::now()->format('Y-m-d H:i:s');

        $conf_autorewrite = <<<CONF
    # Auto-generated Force HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^/(.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
    CONF;

        $conf_http = <<<CONF
<VirtualHost 127.0.0.1:80>
    # Auto-generated by the test environment creator on $date
    ServerName $domain
    ServerAlias www.$domain
    DocumentRoot $documentRoot
    ServerAdmin admin@localhost.net
    UseCanonicalName Off
    Options -ExecCGI -Includes
    RemoveHandler cgi-script .cgi .pl .plx .ppl .perl
    CustomLog \${APACHE_LOG_DIR}/$domain combined
    CustomLog \${APACHE_LOG_DIR}/$domain-bytes_log "%{%s}t %I .$newline%{%s}t %O ."

    # Enable HTTP2
    Protocols h2 http/1.1

    <FilesMatch \.php$>
        # Apache 2.4.10+ can proxy to unix socket
        SetHandler "proxy:unix:/var/run/php/php$phpVersion-fpm-$domain.sock|fcgi://localhost/"
    </FilesMatch>

    <IfModule mod_brotli.c>
        AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css text/javascript application/x-javascript application/javascript application/json application/x-font-ttf application/vnd.ms-fontobject image/x-icon
    </IfModule>

    <Directory $documentRoot/>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        order allow,deny
        allow from all
        Require all granted
    </Directory>

    ##AUTOREWRITE##
</VirtualHost>

CONF;

        $conf_https = <<<CONF
<IfModule mod_ssl.c>
    <VirtualHost 127.0.0.1:443>
        # Auto-generated by the test environment creator on $date
        ServerName $domain
        ServerAlias www.$domain
        DocumentRoot $documentRoot
        ServerAdmin admin@localhost.net
        UseCanonicalName Off
        Options -ExecCGI -Includes
        RemoveHandler cgi-script .cgi .pl .plx .ppl .perl
        CustomLog \${APACHE_LOG_DIR}/ssl-$domain combined
        CustomLog \${APACHE_LOG_DIR}/ssl-$domain-bytes_log "%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"

        # Enable HTTP2
        Protocols h2 http/1.1

        <FilesMatch "\.(cgi|shtml|phtml|php)$">
            SSLOptions +StdEnvVars
        </FilesMatch>

        <Directory $documentRoot/>
            Options -Indexes +FollowSymLinks
            AllowOverride All
            order allow,deny
            allow from all
            Require all granted
        </Directory>

        <IfModule mod_brotli.c>
            AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css text/javascript application/x-javascript application/javascript application/json application/x-font-ttf application/vnd.ms-fontobject image/x-icon
        </IfModule>

        <FilesMatch \.php$>
            # Apache 2.4.10+ can proxy to unix socket
            SetHandler "proxy:unix:/var/run/php/php$phpVersion-fpm-$domain.sock|fcgi://localhost/"
        </FilesMatch>

        SSLEngine on
        SSLCipherSuite ALL:!ADH:!EXPORT56:RC4+RSA:+HIGH:+MEDIUM:+LOW:+SSLv2:+EXP
        SSLCertificateFile "/etc/apache2/certs-selfsigned/$domain.crt"
        SSLCertificateKeyFile "/etc/apache2/certs-selfsigned/$domain.key"
    </VirtualHost>
</IfModule>

CONF;
        if ($enableHttps) {
            if ($forceHttps) {
                $conf_http = str_replace('##AUTOREWRITE##', $conf_autorewrite, $conf_http);
            } else {
                $conf_http = str_replace('##AUTOREWRITE##', '', $conf_http);
            }

            return implode(PHP_EOL, [$conf_http, $conf_https]);
        } else {
            return str_replace('##AUTOREWRITE##', '', $conf_http);
        }
    }
}
