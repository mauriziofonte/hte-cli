<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\HostsManagerInterface;

class HostsCommand extends CommandWrapper
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'hosts
    {action? : Action to perform (list, sync, add, remove)}
    {--domain= : Domain name for add/remove actions}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Manage /etc/hosts entries for VirtualHosts created by hte-cli';

    /**
     * Apache manager.
     *
     * @var ApacheManagerInterface
     */
    private $apache;

    /**
     * Hosts manager.
     *
     * @var HostsManagerInterface
     */
    private $hosts;

    /**
     * HostsCommand constructor.
     *
     * @param ApacheManagerInterface $apache
     * @param HostsManagerInterface  $hosts
     */
    public function __construct(ApacheManagerInterface $apache, HostsManagerInterface $hosts)
    {
        parent::__construct();
        $this->apache = $apache;
        $this->hosts = $hosts;
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

        $action = $this->argument('action');

        // If no action specified, show help
        if (empty($action)) {
            $this->showHelp();
            return;
        }

        switch (strtolower($action)) {
            case 'list':
                $this->listDomains();
                break;
            case 'sync':
                $this->syncDomains();
                break;
            case 'add':
                $this->addDomain();
                break;
            case 'remove':
                $this->removeDomain();
                break;
            default:
                $this->error("Unknown action: {$action}");
                $this->showHelp();
                break;
        }
    }

    /**
     * Show help information.
     *
     * @return void
     */
    private function showHelp(): void
    {
        $this->info("HTE-CLI Hosts Management");
        $this->newLine();
        $this->line("Available actions:");
        $this->line("  list    - Show all domains in /etc/hosts managed by hte-cli");
        $this->line("  sync    - Synchronize /etc/hosts with configured VirtualHosts");
        $this->line("  add     - Add a domain to /etc/hosts (requires --domain)");
        $this->line("  remove  - Remove a domain from /etc/hosts (requires --domain)");
        $this->newLine();
        $this->line("Examples:");
        $this->line("  hte-cli hosts list");
        $this->line("  hte-cli hosts sync");
        $this->line("  hte-cli hosts add --domain=myapp.test");
        $this->line("  hte-cli hosts remove --domain=myapp.test");
    }

    /**
     * List all domains managed by hte-cli in /etc/hosts.
     *
     * @return void
     */
    private function listDomains(): void
    {
        $htecliDomains = $this->hosts->listManagedDomains();

        if (empty($htecliDomains)) {
            $this->info("No domains in /etc/hosts are managed by hte-cli.");
            return;
        }

        $this->info("Domains in /etc/hosts managed by hte-cli:");
        $this->newLine();

        foreach ($htecliDomains as $domain) {
            $this->line("  - {$domain}");
        }

        $this->newLine();
        $this->line("Total: " . count($htecliDomains) . " domain(s)");
    }

    /**
     * Synchronize /etc/hosts with configured VirtualHosts.
     *
     * @return void
     */
    private function syncDomains(): void
    {
        // require root privileges to modify /etc/hosts
        $this->requirePrivileges();

        // Get all configured VirtualHosts
        $vhosts = $this->apache->getVhostsList();
        $domains = array_column($vhosts, 'domain');

        if (empty($domains)) {
            $this->warn("No VirtualHosts configured. Nothing to sync.");
            return;
        }

        $this->info("Synchronizing /etc/hosts with " . count($domains) . " VirtualHost(s)...");
        $this->newLine();

        // Show what will be synced
        $this->line("Domains to sync:");
        foreach ($domains as $domain) {
            $this->line("  - {$domain}");
        }
        $this->newLine();

        // Get current hte-cli managed domains
        $currentDomains = $this->hosts->listManagedDomains();
        $toAdd = array_diff($domains, $currentDomains);
        $toRemove = array_diff($currentDomains, $domains);

        if (empty($toAdd) && empty($toRemove)) {
            $this->info("/etc/hosts is already in sync.");
            return;
        }

        if (!empty($toAdd)) {
            $this->line("Will add: " . implode(', ', $toAdd));
        }
        if (!empty($toRemove)) {
            $this->line("Will remove: " . implode(', ', $toRemove));
        }
        $this->newLine();

        // Perform sync
        if ($this->hosts->sync($domains)) {
            $this->info("/etc/hosts synchronized successfully!");
            if (!empty($toAdd)) {
                $this->line("Added: " . count($toAdd) . " domain(s)");
            }
            if (!empty($toRemove)) {
                $this->line("Removed: " . count($toRemove) . " domain(s)");
            }
        } else {
            $this->error("Failed to synchronize /etc/hosts. Make sure you have write permissions.");
        }
    }

    /**
     * Add a domain to /etc/hosts.
     *
     * @return void
     */
    private function addDomain(): void
    {
        // require root privileges to modify /etc/hosts
        $this->requirePrivileges();

        $domain = $this->option('domain');

        if (empty($domain)) {
            $domain = $this->keepAsking('Enter the domain name to add', '', function ($answer) {
                return validate_domain($answer) !== null;
            });
        }

        if (!validate_domain($domain)) {
            $this->criticalError("Invalid domain name: {$domain}");
        }

        if ($this->hosts->exists($domain)) {
            $entry = $this->hosts->getEntry($domain);
            if ($entry && $entry['managed_by_hte_cli']) {
                $this->warn("Domain {$domain} is already in /etc/hosts (managed by hte-cli).");
            } else {
                $this->warn("Domain {$domain} already exists in /etc/hosts (not managed by hte-cli).");
            }
            return;
        }

        if ($this->hosts->add($domain)) {
            $this->info("Added {$domain} to /etc/hosts");
            $this->line("   Entry: 127.0.0.1    {$domain} www.{$domain}");
        } else {
            $this->error("Failed to add {$domain} to /etc/hosts");
        }
    }

    /**
     * Remove a domain from /etc/hosts.
     *
     * @return void
     */
    private function removeDomain(): void
    {
        // require root privileges to modify /etc/hosts
        $this->requirePrivileges();

        $domain = $this->option('domain');

        if (empty($domain)) {
            // Show list of hte-cli managed domains to choose from
            $htecliDomains = $this->hosts->listManagedDomains();

            if (empty($htecliDomains)) {
                $this->warn("No domains in /etc/hosts are managed by hte-cli.");
                return;
            }

            $this->line("Domains managed by hte-cli:");
            foreach ($htecliDomains as $idx => $d) {
                $this->line("  [{$idx}] {$d}");
            }
            $this->newLine();

            $domain = $this->keepAsking('Enter the domain name to remove', '', function ($answer) use ($htecliDomains) {
                return in_array($answer, $htecliDomains);
            });
        }

        if (!$this->hosts->exists($domain)) {
            $this->warn("Domain {$domain} is not in /etc/hosts.");
            return;
        }

        $entry = $this->hosts->getEntry($domain);
        if ($entry && !$entry['managed_by_hte_cli']) {
            $this->warn("Domain {$domain} was not added by hte-cli. Remove it manually if needed.");
            $this->line("   Current entry: {$entry['content']}");
            return;
        }

        if ($this->hosts->remove($domain)) {
            $this->info("Removed {$domain} from /etc/hosts");
        } else {
            $this->error("Failed to remove {$domain} from /etc/hosts");
        }
    }
}
