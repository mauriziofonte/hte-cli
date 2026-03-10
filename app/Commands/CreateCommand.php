<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\PhpFpmManagerInterface;
use Mfonte\HteCli\Contracts\HostsManagerInterface;
use Mfonte\HteCli\Contracts\SslCertManagerInterface;
use Mfonte\HteCli\Contracts\ServiceManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Contracts\ProcessExecutorInterface;
use Mfonte\HteCli\Logic\Preprocessors\PhpProfiles;

/**
 * Creates a new LAMP test environment (Apache VirtualHost + PHP-FPM pool).
 *
 * Accepts domain, docroot, PHP version, SSL flags, /etc/hosts injection,
 * and a PHP-FPM hardening profile either via CLI options or interactive prompts.
 * All privileged operations are delegated to injected service contracts so the
 * command logic is fully testable without touching the real filesystem or daemons.
 */
class CreateCommand extends CommandWrapper
{
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
    {--forcessl= : Force the VirtualHost to use HTTPS}
    {--hosts= : Add domain to /etc/hosts automatically}
    {--profile= : PHP-FPM hardening profile (dev, staging, hardened)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new LAMP Test Environment (Apache/PHP-FPM) for a specific Domain Name';

    /** @var ApacheManagerInterface */
    private $apache;

    /** @var PhpFpmManagerInterface */
    private $phpFpm;

    /** @var HostsManagerInterface */
    private $hosts;

    /** @var SslCertManagerInterface */
    private $sslCert;

    /** @var ServiceManagerInterface */
    private $services;

    /** @var FilesystemInterface */
    private $fs;

    /**
     * @param ApacheManagerInterface  $apache
     * @param PhpFpmManagerInterface  $phpFpm
     * @param HostsManagerInterface   $hosts
     * @param SslCertManagerInterface $sslCert
     * @param ServiceManagerInterface $services
     * @param FilesystemInterface     $fs
     */
    public function __construct(
        ApacheManagerInterface $apache,
        PhpFpmManagerInterface $phpFpm,
        HostsManagerInterface $hosts,
        SslCertManagerInterface $sslCert,
        ServiceManagerInterface $services,
        FilesystemInterface $fs
    ) {
        parent::__construct();
        $this->apache   = $apache;
        $this->phpFpm   = $phpFpm;
        $this->hosts    = $hosts;
        $this->sslCert  = $sslCert;
        $this->services = $services;
        $this->fs       = $fs;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        // pre-flight stuff: OS version, enabled functions, and root privileges
        $this->preRun();

        // if the current user cannot run privileged commands, we cannot do anything
        $this->requirePrivileges();

        // if the current user is root, we cannot run this command: we need a regular user
        $this->requireNonRootUser();

        // list installed PHP versions
        $phpVers = $this->phpFpm->getInstalledVersions();

        // collect command line options or nullify if not provided (ask the user for them)
        $domain   = $this->option('domain') ? $this->option('domain') : null;
        $docroot  = $this->option('docroot') ? $this->option('docroot') : null;
        $phpver   = $this->option('phpver') ? $this->option('phpver') : null;
        $ssl      = $this->option('ssl') ? answer_to_bool($this->option('ssl')) : null;
        $forcessl = $this->option('forcessl') ? answer_to_bool($this->option('forcessl')) : null;
        $addHosts = $this->option('hosts') ? answer_to_bool($this->option('hosts')) : null;
        $profile  = $this->option('profile') ? $this->option('profile') : null;

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
            $vers   = implode(', ', $phpVers);
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

        // ask about /etc/hosts
        if ($addHosts === null) {
            $addHosts = $this->keepAsking('Add domain to /etc/hosts? ["yes", "no", "y" or "n"]', 'y', function ($answer) {
                return in_array(strtolower($answer), ['yes', 'no', 'y', 'n', '1', 'true']);
            });
        }

        // ask about PHP-FPM profile
        $validProfiles = [PhpProfiles::DEV, PhpProfiles::STAGING, PhpProfiles::HARDENED];
        if (empty($profile) || !in_array($profile, $validProfiles)) {
            $profilesList = implode(', ', $validProfiles);
            $profile = $this->keepAsking("PHP-FPM hardening profile ({$profilesList})", PhpProfiles::DEV, function ($answer) use ($validProfiles) {
                return in_array(strtolower($answer), $validProfiles);
            });
            $profile = strtolower($profile);
        }

        // check for duplication of a config with the same domain name
        $duplicate = $this->apache->getConfByDomain($domain);
        if ($duplicate) {
            $this->criticalError("A VirtualHost for {$domain} already exists. Please choose another domain name.");
        }

        // create the VHost configuration
        $vhostConf = $this->apache->createVhost($domain, $docroot, $phpver, $ssl, $forcessl);
        if (empty($vhostConf)) {
            $this->criticalError("Failed to write to disk the VirtualHost configuration for {$domain}.");
        }
        $this->line("⏳ VirtualHost configuration for {$domain} created at {$vhostConf}");

        // create the PHP-FPM configuration with the selected profile
        $fpmConf = $this->phpFpm->writeConf(
            $domain,
            $docroot,
            $phpver,
            $this->userContext->getUserName(),
            $this->userContext->getUserGroup(),
            $profile
        );
        if (empty($fpmConf)) {
            $this->fs->delete($vhostConf);
            $this->criticalError("Failed to write to disk the PHP{$phpver}-FPM configuration for {$domain}.");
        }
        $this->line("⏳ PHP{$phpver}-FPM configuration for {$domain} created at {$fpmConf} (profile: {$profile})");

        // create the SSL certificate (if needed)
        if ($ssl) {
            $certScript = $this->sslCert->generateScript($domain);

            // write the script to a temporary file
            $tmpFile = $this->fs->tempFile(sys_get_temp_dir(), "sscert_{$domain}");
            if ($tmpFile && $this->fs->putContents($tmpFile, $certScript) && $this->fs->fileExists($tmpFile)) {
                // make it executable
                $this->fs->chmod($tmpFile, 0755);
                $this->line("⏳ Self-signed SSL certificate script for {$domain} created at {$tmpFile}");
            } else {
                $this->fs->delete($tmpFile);
                $this->fs->delete($vhostConf);
                $this->fs->delete($fpmConf);
                $this->criticalError("Failed to write to disk the self-signed SSL certificate script for {$domain}.");
            }

            // execute the script
            $this->line("🔐️ Executing the self-signed SSL certificate script for {$domain}...");
            list($exitCode, $output, $error) = $this->process->execute($tmpFile);
            $this->warn($output);
            $this->fs->delete($tmpFile);
            if ($exitCode != 0) {
                $this->fs->delete($vhostConf);
                $this->fs->delete($fpmConf);
                $this->criticalError("Failed to execute the self-signed SSL certificate script for {$domain}. Stderr: {$error}");
            }
        }

        // enable the VirtualHost
        $vhostConfName = str_replace('.conf', '', basename($vhostConf));
        $this->line("⏳ Enabling {$domain} on config {$vhostConfName}...");
        list($exitCode, $output, $error) = $this->services->enableSite($vhostConfName);
        if ($exitCode != 0) {
            $this->criticalError("Failed to enable the VirtualHost for {$domain}. Stderr: {$error}");
        }

        // restart Apache2
        $this->line("⚡ Restarting Apache2...");
        list($exitCode, $output, $error) = $this->services->restartApache();
        if ($exitCode != 0) {
            $this->criticalError("Failed to restart Apache2. Stderr: {$error}");
        }

        // restart PHP{$phpver}-FPM
        $this->line("⚡ Restarting PHP{$phpver}-FPM...");
        list($exitCode, $output, $error) = $this->services->restartPhpFpm($phpver);
        if ($exitCode != 0) {
            $this->criticalError("Failed to restart PHP{$phpver}-FPM. Stderr: {$error}");
        }

        // add domain to /etc/hosts if requested
        if (answer_to_bool($addHosts)) {
            $this->line("⏳ Adding {$domain} to /etc/hosts...");
            if ($this->hosts->exists($domain)) {
                $this->warn("⚠️ Domain {$domain} already exists in /etc/hosts, skipping.");
            } else {
                if ($this->hosts->add($domain)) {
                    $this->line("✅ Added {$domain} to /etc/hosts");
                } else {
                    $this->warn("⚠️ Failed to add {$domain} to /etc/hosts. You may need to add it manually.");
                }
            }
        }

        // completed!
        $this->info("✅ VirtualHost {$domain} created successfully!");
    }
}
