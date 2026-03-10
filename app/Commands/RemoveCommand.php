<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Contracts\HostsManagerInterface;
use Mfonte\HteCli\Contracts\PhpFpmManagerInterface;
use Mfonte\HteCli\Contracts\ServiceManagerInterface;
use Mfonte\HteCli\Traits\InteractiveMenu;

/**
 * RemoveCommand removes an existing VirtualHost configuration managed by HTE-Cli.
 *
 * Handles disabling the Apache site, removing Apache and PHP-FPM config files,
 * removing SSL certificates, cleaning up /etc/hosts entries, restarting services,
 * and normalizing VirtualHost indexes after deletion.
 */
class RemoveCommand extends CommandWrapper
{
    use InteractiveMenu;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'remove
    {--domain= : The Domain name you want to remove}
    {--force : Skip confirmation prompt}
    {--no-normalize : Skip index normalization after removal}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Delete a VirtualHost configuration created by HTE-Cli';

    /** @var ApacheManagerInterface */
    private $apache;

    /** @var PhpFpmManagerInterface */
    private $phpFpm;

    /** @var HostsManagerInterface */
    private $hosts;

    /** @var ServiceManagerInterface */
    private $services;

    /** @var FilesystemInterface */
    private $fs;

    public function __construct(
        ApacheManagerInterface $apache,
        PhpFpmManagerInterface $phpFpm,
        HostsManagerInterface $hosts,
        ServiceManagerInterface $services,
        FilesystemInterface $fs
    ) {
        parent::__construct();
        $this->apache = $apache;
        $this->phpFpm = $phpFpm;
        $this->hosts = $hosts;
        $this->services = $services;
        $this->fs = $fs;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // pre-flight stuff: OS version, enabled functions, and root privileges
        $this->preRun();

        // if the current user cannot run privileged commands, we cannot do anything
        $this->requirePrivileges();

        // get a list of the current vhosts
        $currentVhosts = $this->apache->getVhostsList();

        if (count($currentVhosts) === 0) {
            $this->warn('No VirtualHosts found. Nothing to remove.');
            return;
        }

        // Show current vhosts table
        $currentVhostsForTable = collect($currentVhosts)->map(function ($vhost) {
            return [
                'index' => $vhost['index'],
                'domain' => $vhost['domain'],
                'phpver' => $vhost['phpver'],
                'ssl' => $vhost['ssl'] ? 'Yes' : 'No',
                'docroot' => $vhost['docroot'],
                'enabled' => $vhost['enabled'] ? 'Yes' : 'No',
            ];
        })->toArray();

        $this->table(['Index', 'Domain', 'PHP', 'SSL', 'Document Root', 'Enabled'], $currentVhostsForTable);
        $this->newLine();

        // Get domain from option or interactive selection
        $domain = $this->option('domain');
        $availableDomains = array_column($currentVhosts, 'domain');

        if (empty($domain) || !in_array($domain, $availableDomains)) {
            // Interactive mode: show menu
            $this->newLine();
            $domainOptions = array_combine($availableDomains, $availableDomains);
            $domain = $this->interactiveSelect(
                'HTE-Cli: Select the VirtualHost to remove',
                $domainOptions,
                $availableDomains[0]
            );
        }

        // Get the vhost configuration
        $vhostConf = $this->apache->getConfByDomain($domain);
        if (empty($vhostConf)) {
            $this->criticalError("No VirtualHost found for {$domain}");
        }

        // Show what will be removed
        $this->newLine();
        $this->warn("The following will be removed:");
        $this->line("  Domain: {$domain}");
        $this->line("  Apache config: {$vhostConf['conf']}");
        $this->line("  Document Root: {$vhostConf['docroot']} (will NOT be deleted)");

        // Get PHP-FPM config
        $phpFpmConf = $this->phpFpm->getConfByDomain($domain);
        if ($phpFpmConf) {
            $this->line("  PHP-FPM config: {$phpFpmConf['conf']}");
        }

        // Check for SSL certificates
        if ($vhostConf['ssl']) {
            $this->line("  SSL Certificate: {$vhostConf['sslcertfile']}");
            $this->line("  SSL Key: {$vhostConf['sslkeyfile']}");
        }

        // Check /etc/hosts
        if ($this->hosts->exists($domain)) {
            $entry = $this->hosts->getEntry($domain);
            if ($entry && $entry['managed_by_hte_cli']) {
                $this->line("  /etc/hosts entry: Will be removed");
            }
        }

        $this->newLine();

        // Confirm unless --force is used
        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to remove the VirtualHost for {$domain}?", false)) {
                $this->info("Operation cancelled.");
                return;
            }
        }

        $this->newLine();
        $this->info("Removing VirtualHost {$domain}...");

        // 1. Disable the site
        $vhostConfName = str_replace('.conf', '', basename($vhostConf['conf']));
        $this->line("Disabling site {$vhostConfName}...");
        $this->services->disableSite($vhostConfName);

        // 2. Remove the Apache config file
        if ($this->fs->fileExists($vhostConf['conf'])) {
            if ($this->fs->delete($vhostConf['conf'])) {
                $this->info("Removed Apache configuration: {$vhostConf['conf']}");
            } else {
                $this->warn("Could not remove Apache configuration: {$vhostConf['conf']}");
            }
        }

        // 3. Remove PHP-FPM config
        $phpVersion = null;
        if ($phpFpmConf && $this->fs->fileExists($phpFpmConf['conf'])) {
            $phpVersion = $phpFpmConf['phpver'];
            if ($this->fs->delete($phpFpmConf['conf'])) {
                $this->info("Removed PHP{$phpVersion}-FPM configuration: {$phpFpmConf['conf']}");
            } else {
                $this->warn("Could not remove PHP-FPM configuration: {$phpFpmConf['conf']}");
            }
        }

        // 4. Remove SSL certificates if they exist
        if ($vhostConf['ssl']) {
            if ($vhostConf['sslcertfile'] && $this->fs->fileExists($vhostConf['sslcertfile'])) {
                if ($this->fs->delete($vhostConf['sslcertfile'])) {
                    $this->info("Removed SSL certificate: {$vhostConf['sslcertfile']}");
                } else {
                    $this->warn("Could not remove SSL certificate: {$vhostConf['sslcertfile']}");
                }
            }
            if ($vhostConf['sslkeyfile'] && $this->fs->fileExists($vhostConf['sslkeyfile'])) {
                if ($this->fs->delete($vhostConf['sslkeyfile'])) {
                    $this->info("Removed SSL key: {$vhostConf['sslkeyfile']}");
                } else {
                    $this->warn("Could not remove SSL key: {$vhostConf['sslkeyfile']}");
                }
            }
        }

        // 5. Restart Apache
        $this->line("Restarting Apache2...");
        list($exitCode, $output, $error) = $this->services->restartApache();
        if ($exitCode === 0) {
            $this->info("Apache2 restarted successfully");
        } else {
            $this->warn("Apache2 could not be restarted: {$error}");
        }

        // 6. Restart PHP-FPM if we had a config
        if ($phpVersion) {
            $this->line("Restarting PHP{$phpVersion}-FPM...");
            list($exitCode, $output, $error) = $this->services->restartPhpFpm($phpVersion);
            if ($exitCode === 0) {
                $this->info("PHP{$phpVersion}-FPM restarted successfully");
            } else {
                $this->warn("PHP{$phpVersion}-FPM could not be restarted: {$error}");
            }
        }

        // 7. Remove domain from /etc/hosts if it was added by hte-cli
        if ($this->hosts->exists($domain)) {
            $entry = $this->hosts->getEntry($domain);
            if ($entry && $entry['managed_by_hte_cli']) {
                $this->line("Removing {$domain} from /etc/hosts...");
                if ($this->hosts->remove($domain)) {
                    $this->info("Removed {$domain} from /etc/hosts");
                } else {
                    $this->warn("Could not remove {$domain} from /etc/hosts. You may need to remove it manually.");
                }
            }
        }

        // 8. Normalize indexes unless --no-normalize is used
        if (!$this->option('no-normalize')) {
            $this->newLine();
            $this->line("Normalizing VirtualHost indexes...");
            $normalizeResult = $this->apache->normalizeIndexes();

            if ($normalizeResult['renamed'] > 0) {
                $this->info("Renumbered {$normalizeResult['renamed']} VirtualHost(s) to maintain sequential indexes");
            } else {
                $this->line("No renumbering needed");
            }

            if (!empty($normalizeResult['errors'])) {
                foreach ($normalizeResult['errors'] as $error) {
                    $this->warn("Normalization error: {$error}");
                }
            }
        }

        // Done!
        $this->newLine();
        $this->info("VirtualHost {$domain} removed successfully!");
    }
}
