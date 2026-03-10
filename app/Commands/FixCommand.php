<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\ServiceManagerInterface;
use Mfonte\HteCli\Traits\InteractiveMenu;

class FixCommand extends CommandWrapper
{
    use InteractiveMenu;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'fix
    {--domain= : Fix a specific domain only}
    {--force : Skip confirmation prompt}
    {--dry-run : Show what would be fixed without making changes}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Fix old VirtualHost configurations to add localhost HTTPS bypass';

    /**
     * Apache manager.
     *
     * @var ApacheManagerInterface
     */
    private $apache;

    /**
     * Service manager.
     *
     * @var ServiceManagerInterface
     */
    private $services;

    /**
     * FixCommand constructor.
     *
     * @param ApacheManagerInterface  $apache
     * @param ServiceManagerInterface $services
     */
    public function __construct(ApacheManagerInterface $apache, ServiceManagerInterface $services)
    {
        parent::__construct();
        $this->apache = $apache;
        $this->services = $services;
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

        // if not dry-run, require root privileges
        if (!$this->option('dry-run')) {
            $this->requirePrivileges();
        }

        // get a list of the current vhosts
        $currentVhosts = $this->apache->getVhostsList();

        if (count($currentVhosts) === 0) {
            $this->warn('No VirtualHosts found. Nothing to fix.');
            return;
        }

        // Filter to only vhosts with forcedssl that need fixing
        $vhostsNeedingFix = [];
        $specificDomain = $this->option('domain');

        foreach ($currentVhosts as $vhost) {
            // Skip if specific domain requested and this isn't it
            if ($specificDomain && $vhost['domain'] !== $specificDomain) {
                continue;
            }

            // Only check vhosts with Force HTTPS enabled
            if (!$vhost['forcedssl']) {
                continue;
            }

            // Check if this vhost needs fixing
            $needsFix = $this->apache->needsLocalhostBypassFix($vhost['conf']);
            if ($needsFix) {
                $vhostsNeedingFix[] = $vhost;
            }
        }

        if (count($vhostsNeedingFix) === 0) {
            if ($specificDomain) {
                $this->info("VirtualHost {$specificDomain} does not need fixing or was not found.");
                // Check if there's an .htaccess overriding for this specific domain
                $vhost = $this->apache->getConfByDomain($specificDomain);
                if ($vhost && !empty($vhost['docroot'])) {
                    $htaccessCheck = $this->apache->hasHtaccessHttpsRedirect($vhost['docroot']);
                    if ($htaccessCheck['has_redirect']) {
                        $this->newLine();
                        $this->warn("NOTE: This VirtualHost has an .htaccess with HTTPS redirect rules.");
                        $this->warn("The .htaccess rules override the VirtualHost config and may still force HTTPS.");
                        $this->line("  File: {$htaccessCheck['file']} (line {$htaccessCheck['line']})");
                        $this->newLine();
                        $this->line("To allow HTTP from localhost, add these lines BEFORE the HTTPS redirect in .htaccess:");
                        $this->line("  RewriteCond %{REMOTE_ADDR} !^127\\.0\\.0\\.1\$");
                        $this->line("  RewriteCond %{REMOTE_ADDR} !^::1\$");
                    }
                }
            } else {
                $this->info("All VirtualHosts are already up-to-date with localhost HTTPS bypass.");
            }
            return;
        }

        // Show what needs to be fixed
        $this->newLine();
        $this->info("Found " . count($vhostsNeedingFix) . " VirtualHost(s) that need the localhost HTTPS bypass fix:");
        $this->newLine();

        $tableData = collect($vhostsNeedingFix)->map(function ($vhost) {
            return [
                'index'  => $vhost['index'],
                'domain' => $vhost['domain'],
                'phpver' => $vhost['phpver'],
                'conf'   => basename($vhost['conf']),
            ];
        })->toArray();

        $this->table(['Index', 'Domain', 'PHP', 'Config File'], $tableData);

        // Explain what the fix does
        $this->newLine();
        $this->line("This fix will update the Force HTTPS rewrite rules to bypass localhost requests.");
        $this->line("After fixing, HTTP requests from 127.0.0.1 and ::1 will not be redirected to HTTPS.");
        $this->newLine();

        // Dry-run mode
        if ($this->option('dry-run')) {
            $this->warn("Dry-run mode: No changes will be made.");
            $this->newLine();
            $this->info("The following changes would be applied:");
            foreach ($vhostsNeedingFix as $vhost) {
                $this->line("  - {$vhost['domain']}: Add localhost bypass to Force HTTPS rule");
            }
            return;
        }

        // Confirm unless --force is used
        if (!$this->option('force')) {
            if (!$this->confirm("Do you want to apply the fix to these VirtualHost(s)?", false)) {
                $this->info("Operation cancelled.");
                return;
            }
        }

        $this->newLine();
        $this->info("Applying fixes...");

        $fixedCount = 0;
        $errors = [];

        foreach ($vhostsNeedingFix as $vhost) {
            $this->line("Fixing {$vhost['domain']}...");

            $result = $this->apache->fixLocalhostBypass($vhost['conf']);

            if ($result['success']) {
                $this->info("  Fixed: {$vhost['domain']}");
                $fixedCount++;
            } else {
                $this->error("  Failed to fix {$vhost['domain']}: {$result['error']}");
                $errors[] = $vhost['domain'] . ': ' . $result['error'];
            }
        }

        // Restart Apache if any fixes were applied
        if ($fixedCount > 0) {
            $this->newLine();
            $this->line("Restarting Apache2...");
            if ($this->services->restartApache()) {
                $this->info("Apache2 restarted successfully");
            } else {
                $this->warn("Apache2 could not be restarted.");
            }
        }

        // Summary
        $this->newLine();
        if ($fixedCount === count($vhostsNeedingFix)) {
            $this->info("All {$fixedCount} VirtualHost(s) have been fixed successfully!");
        } else {
            $this->warn("Fixed {$fixedCount} of " . count($vhostsNeedingFix) . " VirtualHost(s).");
            if (!empty($errors)) {
                $this->newLine();
                $this->error("Errors encountered:");
                foreach ($errors as $err) {
                    $this->line("  - {$err}");
                }
            }
        }

        // Check for .htaccess HTTPS redirects that would override the VirtualHost config
        $htaccessWarnings = [];
        foreach ($vhostsNeedingFix as $vhost) {
            if (!empty($vhost['docroot'])) {
                $htaccessCheck = $this->apache->hasHtaccessHttpsRedirect($vhost['docroot']);
                if ($htaccessCheck['has_redirect']) {
                    $htaccessWarnings[] = [
                        'domain' => $vhost['domain'],
                        'file'   => $htaccessCheck['file'],
                        'line'   => $htaccessCheck['line'],
                    ];
                }
            }
        }

        if (!empty($htaccessWarnings)) {
            $this->newLine();
            $this->warn("WARNING: The following VirtualHosts have .htaccess files with HTTPS redirect rules.");
            $this->warn("These rules will override the VirtualHost localhost bypass and still force HTTPS.");
            $this->newLine();
            foreach ($htaccessWarnings as $warning) {
                $this->line("  - {$warning['domain']}: {$warning['file']} (line {$warning['line']})");
            }
            $this->newLine();
            $this->line("To fix this, you need to modify the .htaccess file(s) to add localhost bypass:");
            $this->newLine();
            $this->line("  # Replace this pattern:");
            $this->line("  RewriteCond %{HTTPS} off");
            $this->line("  RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]");
            $this->newLine();
            $this->line("  # With this (adds localhost bypass):");
            $this->line("  RewriteCond %{HTTPS} off");
            $this->line("  RewriteCond %{REMOTE_ADDR} !^127\\.0\\.0\\.1\$");
            $this->line("  RewriteCond %{REMOTE_ADDR} !^::1\$");
            $this->line("  RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]");
        }

        // Show verification hint
        $this->newLine();
        $this->line("To verify the fix, run:");
        $this->line("  curl -v http://<domain>");
        $this->line("HTTP requests from localhost should now work without HTTPS redirect.");
    }
}
