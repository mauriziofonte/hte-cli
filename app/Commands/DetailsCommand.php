<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Contracts\HostsManagerInterface;
use Mfonte\HteCli\Contracts\PhpFpmManagerInterface;
use Mfonte\HteCli\Logic\Preprocessors\PhpProfiles;
use Mfonte\HteCli\Traits\InteractiveMenu;

class DetailsCommand extends CommandWrapper
{
    use InteractiveMenu;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'details
    {--domain= : Show details for a specific domain}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Lists all details of available LAMP Test Environments';

    /**
     * Apache manager.
     *
     * @var ApacheManagerInterface
     */
    private $apache;

    /**
     * PHP-FPM manager.
     *
     * @var PhpFpmManagerInterface
     */
    private $phpFpm;

    /**
     * Hosts manager.
     *
     * @var HostsManagerInterface
     */
    private $hosts;

    /**
     * Filesystem abstraction.
     *
     * @var FilesystemInterface
     */
    private $fs;

    /**
     * DetailsCommand constructor.
     *
     * @param ApacheManagerInterface $apache
     * @param PhpFpmManagerInterface $phpFpm
     * @param HostsManagerInterface  $hosts
     * @param FilesystemInterface    $fs
     */
    public function __construct(
        ApacheManagerInterface $apache,
        PhpFpmManagerInterface $phpFpm,
        HostsManagerInterface $hosts,
        FilesystemInterface $fs
    ) {
        parent::__construct();
        $this->apache = $apache;
        $this->phpFpm = $phpFpm;
        $this->hosts = $hosts;
        $this->fs = $fs;
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

        // get a list of the current vhosts
        $vhostsList = $this->apache->getVhostsList();

        $count = count($vhostsList);
        if ($count === 0) {
            $this->warn('No VirtualHosts found. Create a VirtualHost first using "hte-cli create".');
            $this->line('   More info at https://github.com/mauriziofonte/hte-cli');
            return;
        }

        // Build table with additional info (profile, hosts status)
        $currentVhosts = collect($vhostsList)->map(function ($vhost) {
            $profile = $this->detectProfile($vhost['domain']);
            $hostsStatus = $this->hosts->exists($vhost['domain']) ? 'Yes' : 'No';

            return [
                'index'      => $vhost['index'],
                'domain'     => $vhost['domain'],
                'docroot'    => $vhost['docroot'],
                'phpver'     => $vhost['phpver'],
                'profile'    => $profile,
                'ssl'        => $vhost['ssl'] ? 'Yes' : 'No',
                'forcedssl'  => $vhost['forcedssl'] ? 'Yes' : 'No',
                'hosts'      => $hostsStatus,
                'enabled'    => $vhost['enabled'] ? 'Yes' : 'No',
            ];
        })->toArray();

        $validDomains = array_column($vhostsList, 'domain');

        $this->info("VirtualHosts Count: {$count}");
        $this->table(
            ['#', 'Domain', 'Document Root', 'PHP', 'Profile', 'SSL', 'Force SSL', '/etc/hosts', 'Enabled'],
            $currentVhosts
        );

        // Check if domain was provided via option
        $domain = $this->option('domain');

        if (!empty($domain)) {
            // Validate provided domain
            if (!in_array($domain, $validDomains)) {
                $this->error("Domain '{$domain}' not found in configured VirtualHosts.");
                return;
            }
        } else {
            // Use interactive menu to select domain for details
            $this->newLine();
            $menuOptions = ['(Exit without showing details)' => null];
            foreach ($validDomains as $d) {
                $menuOptions[$d] = $d;
            }

            $domain = $this->interactiveSelect(
                'HTE-Cli: Select a domain to view PHP-FPM details',
                $menuOptions,
                null
            );

            if ($domain === null) {
                return;
            }
        }

        // Show PHP-FPM details for selected domain
        $this->showPhpFpmDetails($domain);
    }

    /**
     * Show PHP-FPM configuration details for a domain.
     *
     * @param string $domain
     * @return void
     */
    private function showPhpFpmDetails(string $domain): void
    {
        $phpFpmConf = $this->phpFpm->getConfByDomain($domain);

        if (empty($phpFpmConf) || !$this->fs->fileExists($phpFpmConf['conf'])) {
            $this->warn("No PHP-FPM configuration found for {$domain}");
            return;
        }

        $this->newLine();
        $this->info("PHP-FPM Configuration for {$domain}:");
        $this->line("  PHP Version: {$phpFpmConf['phpver']}");
        $this->line("  Config File: {$phpFpmConf['conf']}");

        // Detect and show profile
        $profile = $this->detectProfile($domain);
        $profileDesc = PhpProfiles::getDescription($profile);
        $this->line("  Profile: {$profile} ({$profileDesc})");

        $this->newLine();
        $this->info("Configuration Contents:");
        $this->line('---');
        $this->line($this->fs->getContents($phpFpmConf['conf']));
        $this->line('---');
    }

    /**
     * Detect the PHP-FPM profile for a domain.
     *
     * @param string $domain
     * @return string
     */
    private function detectProfile(string $domain): string
    {
        $phpFpmConf = $this->phpFpm->getConfByDomain($domain);

        if (empty($phpFpmConf) || !isset($phpFpmConf['conf'])) {
            return PhpProfiles::DEV;
        }

        return $this->phpFpm->detectProfile($phpFpmConf['conf']);
    }
}
