<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Logic\Daemons\Apache2;
use Mfonte\HteCli\Logic\Preprocessors\Php;
use Mfonte\HteCli\Logic\Bash\Scripts;

class CreateCommand extends CommandWrapper
{
    /**
     * Note to self:
     * https://gist.github.com/BuonOmo/77b75349c517defb01ef1097e72227af
     */

     
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'create
    {--domain= : The Domain name you want to configure}
    {--docroot= : The Document Root you want to configure}
    {--phpver= : The PHP Version you want to use for this VirtualHost}
    {--ssl= : Enable SSL for this VirtualHost}
    {--forcessl= : Force the VirtualHost to use HTTPS}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new LAMP Test Environment (Apache/PHP-FPM) for a specific Domain Name';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // pre-flight stuff: OS version, enabled functions, and root privileges
        $this->preRun();
        
        // list installed PHP versions
        $phpVers = Php::getInstalledVersions();

        // collect command line options or nullify if not provided (ask the user for them)
        $domain = $this->option('domain') ? $this->option('domain') : null;
        $docroot = $this->option('docroot') ? $this->option('docroot') : null;
        $phpver = $this->option('phpver') ? $this->option('phpver') : null;
        $ssl = $this->option('ssl') ? answer_to_bool($this->option('ssl')) : null;
        $forcessl = $this->option('forcessl') ? answer_to_bool($this->option('forcessl')) : null;

        dd([
            'domain' => $domain,
            'docroot' => $docroot,
            'phpver' => $phpver,
            'ssl' => $ssl,
            'forcessl' => $forcessl,
        ]);

        if (empty($domain) || !validate_domain($domain)) {
            $domain = $this->keepAsking('Enter a valid local Domain Name (suggested .test TLD, as "jane.local.test")', "", function ($answer) {
                return validate_domain($answer);
            });

            // linearize the domain name
            $domain = strtolower(str_replace(['http://', 'https://', 'www.'], '', $domain));
        }
        if (empty($docroot) || !is_dir($docroot)) {
            $docroot = $this->keepAsking('Enter a valid directory in the filesystem for the DocumentRoot', getcwd(), function ($answer) {
                return is_dir($answer);
            });
        }
        if (empty($phpver) || !in_array($phpver, $phpVers)) {
            $vers = implode(', ', $phpVers);
            $phpver = $this->keepAsking("Enter a valid PHP version for PHP-FPM ($vers)", last($phpVers), function ($answer) use ($phpVers) {
                return in_array($answer, $phpVers);
            });
        }
        if ($ssl === null) {
            $ssl = $this->keepAsking('Do you need HTTPS support? ["yes", "no", "y" or "n"]', 'y', function ($answer) {
                return in_array(strtolower($answer), ['yes', 'no', 'y', 'n', '1', 'true']);
            });
        }
        if ($ssl && $forcessl === null) {
            $forcessl = $this->keepAsking('Do you want to force HTTPS? ["yes", "no", "y" or "n"]', 'y', function ($answer) {
                return in_array(strtolower($answer), ['yes', 'no', 'y', 'n', '1', 'true']);
            });
        }

        // check for duplication of a config with the same domain name
        $duplicate = Apache2::getConfByDomain($domain);
        if ($duplicate) {
            $this->criticalError("A VirtualHost for {$domain} already exists. Please choose another domain name.");
        }

        // create the VHost configuration
        $vhostConf = Apache2::createVhost($domain, $docroot, $phpver, $ssl, $forcessl);
        if (empty($vhostConf)) {
            $this->criticalError("Failed to write to disk the VirtualHost configuration for {$domain}.");
        }
        $this->line("⏳ VirtualHost configuration for {$domain} created at {$vhostConf}");

        // create the PHP-FMP configuration
        $fpmConf = Php::writeConf($domain, $docroot, $phpver, $this->userName, $this->userGroup);
        if (empty($fpmConf)) {
            @unlink($vhostConf);
            $this->criticalError("Failed to write to disk the PHP{$phpver}-FPM configuration for {$domain}.");
        }
        $this->line("⏳ PHP{$phpver}-FPM configuration for {$domain} created at {$fpmConf}");

        // create the SSL certificate (if needed)
        if ($ssl) {
            $certScript = Scripts::getSelfSignedCertScript($domain);
            
            // write the script to a temporary file
            $tmpFile = tempnam(sys_get_temp_dir(), "sscert_{$domain}");
            // write the script contents
            if (file_put_contents($tmpFile, $certScript) && is_file($tmpFile)) {
                // make it executable
                chmod($tmpFile, 0755);
                $this->line("⏳ Self-signed SSL certificate script for {$domain} created at {$tmpFile}");
            } else {
                @unlink($tmpFile);
                @unlink($vhostConf);
                @unlink($fpmConf);
                $this->criticalError("Failed to write to disk the self-signed SSL certificate script for {$domain}.");
            }
            // execute the script
            $this->line("🔐️ Executing the self-signed SSL certificate script for {$domain}...");
            list($output, $retval) = $this->shellExecute($tmpFile);
            $this->warn($output);
            @unlink($tmpFile);
            if ($retval != 0) {
                @unlink($vhostConf);
                @unlink($fpmConf);
                $this->criticalError("Failed to execute the self-signed SSL certificate script for {$domain}.");
            }
        }

        // enable the VirtualHost
        $vhostConfName = str_replace('.conf', '', basename($vhostConf));
        $this->line("⏳ Enabling {$domain} on config {$vhostConfName}...");
        $this->shellExecute("a2ensite {$vhostConfName}");

        // restart Apache2
        $this->line("⚡ Restarting Apache2...");
        $this->shellExecute('systemctl restart apache2.service');

        // restart PHP{$phpver}-FPM
        $this->line("⚡ Restarting PHP{$phpver}-FPM...");
        $this->shellExecute("systemctl restart php{$phpver}-fpm.service");

        // completed!
        $this->info("✅ VirtualHost {$domain} created successfully!");
    }
}
