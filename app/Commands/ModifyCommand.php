<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\PhpFpmManagerInterface;
use Mfonte\HteCli\Contracts\SslCertManagerInterface;
use Mfonte\HteCli\Contracts\ServiceManagerInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Logic\Preprocessors\PhpProfiles;
use Mfonte\HteCli\Traits\InteractiveMenu;

/**
 * Modify an existing LAMP Test Environment VirtualHost configuration.
 *
 * Supports both interactive (menu-driven) and non-interactive (option-driven) modes.
 * Delegates all I/O, service management, and configuration generation to injected
 * contracts so that the command can be fully tested without touching the filesystem
 * or running system services.
 */
class ModifyCommand extends CommandWrapper
{
    use InteractiveMenu;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'modify
    {--domain= : The Domain name you want to modify}
    {--phpver= : New PHP version for the VirtualHost}
    {--ssl= : Enable or disable SSL (yes/no)}
    {--forcessl= : Force HTTPS (yes/no)}
    {--profile= : PHP-FPM hardening profile (dev, staging, hardened)}
    {--docroot= : New Document Root}
    {--interactive : Force interactive mode}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Modify an existing LAMP Test Environment configuration';

    /** @var ApacheManagerInterface */
    private $apache;

    /** @var PhpFpmManagerInterface */
    private $phpFpm;

    /** @var SslCertManagerInterface */
    private $sslCert;

    /** @var ServiceManagerInterface */
    private $services;

    /** @var FilesystemInterface */
    private $fs;

    /**
     * Current VirtualHost configuration.
     *
     * @var array
     */
    private $vhostConf;

    /**
     * Current PHP-FPM configuration.
     *
     * @var array
     */
    private $phpFpmConf;

    /**
     * Domain being modified.
     *
     * @var string
     */
    private $domain;

    /**
     * Pending changes to apply.
     *
     * @var array
     */
    private $pendingChanges = [];

    /**
     * @param ApacheManagerInterface  $apache
     * @param PhpFpmManagerInterface  $phpFpm
     * @param SslCertManagerInterface $sslCert
     * @param ServiceManagerInterface $services
     * @param FilesystemInterface     $fs
     */
    public function __construct(
        ApacheManagerInterface $apache,
        PhpFpmManagerInterface $phpFpm,
        SslCertManagerInterface $sslCert,
        ServiceManagerInterface $services,
        FilesystemInterface $fs
    ) {
        parent::__construct();
        $this->apache   = $apache;
        $this->phpFpm   = $phpFpm;
        $this->sslCert  = $sslCert;
        $this->services = $services;
        $this->fs       = $fs;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // pre-flight stuff: OS version, enabled functions, and root privileges
        $this->preRun();

        // abort if the current user cannot run privileged commands
        $this->requirePrivileges();

        // get a list of the current vhosts
        $currentVhosts = $this->apache->getVhostsList();

        if (count($currentVhosts) === 0) {
            $this->warn('No VirtualHosts found. Create a VirtualHost first using "hte-cli create".');
            return;
        }

        // Show current vhosts table
        $currentVhostsForTable = collect($currentVhosts)->map(function ($vhost) {
            return [
                'index'    => $vhost['index'],
                'domain'   => $vhost['domain'],
                'phpver'   => $vhost['phpver'],
                'ssl'      => $vhost['ssl'] ? 'Yes' : 'No',
                'forcessl' => $vhost['forcedssl'] ? 'Yes' : 'No',
                'enabled'  => $vhost['enabled'] ? 'Yes' : 'No',
            ];
        })->toArray();

        $this->table(['Index', 'Domain', 'PHP', 'SSL', 'Force SSL', 'Enabled'], $currentVhostsForTable);

        // Determine if we should run in interactive mode
        $hasOptions = $this->option('phpver') || $this->option('ssl') !== null ||
                      $this->option('forcessl') !== null || $this->option('profile') ||
                      $this->option('docroot');
        $interactive = $this->option('interactive') || !$hasOptions;

        // Get domain to modify
        $this->domain = $this->option('domain');
        $availableDomains = array_column($currentVhosts, 'domain');

        if (empty($this->domain) || !in_array($this->domain, $availableDomains)) {
            $this->newLine();
            $domainOptions = array_combine($availableDomains, $availableDomains);
            $this->domain = $this->interactiveSelect(
                'HTE-Cli: Select the domain to modify',
                $domainOptions,
                $availableDomains[0]
            );
        }

        // Load current configuration
        $this->vhostConf = $this->apache->getConfByDomain($this->domain);
        if (empty($this->vhostConf)) {
            $this->criticalError("No VirtualHost found for {$this->domain}");
        }

        $this->phpFpmConf = $this->phpFpm->getConfByDomain($this->domain);
        if (empty($this->phpFpmConf)) {
            $this->criticalError("No PHP-FPM configuration found for {$this->domain}");
        }

        if ($interactive) {
            $this->runInteractiveMode();
        } else {
            $this->runNonInteractiveMode();
        }
    }

    /**
     * Run the command in interactive mode.
     */
    private function runInteractiveMode(): void
    {
        $this->newLine();
        $this->info("Modifying VirtualHost: {$this->domain}");
        $this->showCurrentConfiguration();

        // Main interactive loop
        while (true) {
            $this->newLine();

            // Build the menu with current values
            $menuOptions = $this->buildMenuOptions();

            $menuOptionsAssoc = array_combine($menuOptions, $menuOptions);
            $choice = $this->interactiveSelect(
                'HTE-Cli: What would you like to modify?',
                $menuOptionsAssoc,
                $menuOptions[count($menuOptions) - 1] // Default to last option (Done)
            );

            if ($choice === 'Done - Apply changes and exit' || $choice === 'Cancel - Exit without changes') {
                break;
            }

            $this->handleMenuChoice($choice);
        }

        // Apply changes if user chose "Done"
        if ($choice === 'Done - Apply changes and exit' && !empty($this->pendingChanges)) {
            $this->applyChanges();
        } elseif (empty($this->pendingChanges)) {
            $this->info("No changes to apply.");
        } else {
            $this->warn("Changes discarded.");
        }
    }

    /**
     * Build menu options with current values.
     *
     * @return array
     */
    private function buildMenuOptions(): array
    {
        $currentPhpVer   = $this->pendingChanges['phpver'] ?? $this->vhostConf['phpver'];
        $currentSsl      = $this->pendingChanges['ssl'] ?? $this->vhostConf['ssl'];
        $currentForceSsl = $this->pendingChanges['forcessl'] ?? $this->vhostConf['forcedssl'];
        $currentDocRoot  = $this->pendingChanges['docroot'] ?? $this->vhostConf['docroot'];
        $currentProfile  = $this->pendingChanges['profile'] ?? $this->detectCurrentProfile();

        $sslLabel      = $currentSsl ? 'Enabled' : 'Disabled';
        $forceSslLabel = $currentForceSsl ? 'Yes' : 'No';

        $options = [
            "PHP Version (current: {$currentPhpVer})",
            "SSL (current: {$sslLabel})",
            "Force HTTPS (current: {$forceSslLabel})",
            "PHP-FPM Profile (current: {$currentProfile})",
            "Document Root (current: {$currentDocRoot})",
        ];

        // Add pending changes indicator
        if (!empty($this->pendingChanges)) {
            $options[] = '---';
            $options[] = 'View pending changes';
        }

        $options[] = '---';
        $options[] = 'Done - Apply changes and exit';
        $options[] = 'Cancel - Exit without changes';

        return $options;
    }

    /**
     * Handle menu choice.
     *
     * @param string $choice
     */
    private function handleMenuChoice(string $choice): void
    {
        if (strpos($choice, 'PHP Version') === 0) {
            $this->modifyPhpVersion();
        } elseif (strpos($choice, 'SSL (current') === 0) {
            $this->modifySsl();
        } elseif (strpos($choice, 'Force HTTPS') === 0) {
            $this->modifyForceHttps();
        } elseif (strpos($choice, 'PHP-FPM Profile') === 0) {
            $this->modifyProfile();
        } elseif (strpos($choice, 'Document Root') === 0) {
            $this->modifyDocumentRoot();
        } elseif ($choice === 'View pending changes') {
            $this->showPendingChanges();
        }
    }

    /**
     * Modify PHP version interactively.
     */
    private function modifyPhpVersion(): void
    {
        $installedVersions = $this->phpFpm->getInstalledVersions();
        $currentVersion    = $this->pendingChanges['phpver'] ?? $this->vhostConf['phpver'];

        $versionOptions = array_combine($installedVersions, $installedVersions);
        $newVersion = $this->interactiveSelect(
            'HTE-Cli: Select PHP version',
            $versionOptions,
            $currentVersion
        );

        if ($newVersion !== $this->vhostConf['phpver']) {
            $this->pendingChanges['phpver'] = $newVersion;
            $this->info("PHP version will be changed to {$newVersion}");
        } else {
            unset($this->pendingChanges['phpver']);
            $this->line("PHP version unchanged.");
        }
    }

    /**
     * Modify SSL setting interactively.
     */
    private function modifySsl(): void
    {
        $currentSsl = $this->pendingChanges['ssl'] ?? $this->vhostConf['ssl'];

        $options = [
            'Enable SSL'  => true,
            'Disable SSL' => false,
        ];
        $newSsl = $this->interactiveSelect('HTE-Cli: SSL Configuration', $options, $currentSsl);

        if ($newSsl !== $this->vhostConf['ssl']) {
            $this->pendingChanges['ssl'] = $newSsl;
            $label = $newSsl ? 'enabled' : 'disabled';
            $this->info("SSL will be {$label}");

            // If enabling SSL and force HTTPS not set, ask about it
            if ($newSsl && !isset($this->pendingChanges['forcessl']) && !$this->vhostConf['forcedssl']) {
                if ($this->interactiveConfirm('HTE-Cli: Would you also like to force HTTPS redirects?', false)) {
                    $this->pendingChanges['forcessl'] = true;
                    $this->info("Force HTTPS will also be enabled");
                }
            }
        } else {
            unset($this->pendingChanges['ssl']);
            $this->line("SSL setting unchanged.");
        }
    }

    /**
     * Modify Force HTTPS setting interactively.
     */
    private function modifyForceHttps(): void
    {
        $currentSsl = $this->pendingChanges['ssl'] ?? $this->vhostConf['ssl'];

        if (!$currentSsl) {
            $this->warn("Force HTTPS requires SSL to be enabled. Enable SSL first.");
            return;
        }

        $currentForceSsl = $this->pendingChanges['forcessl'] ?? $this->vhostConf['forcedssl'];

        $options = [
            'Enable Force HTTPS'  => true,
            'Disable Force HTTPS' => false,
        ];
        $newForceSsl = $this->interactiveSelect('HTE-Cli: Force HTTPS Configuration', $options, $currentForceSsl);

        if ($newForceSsl !== $this->vhostConf['forcedssl']) {
            $this->pendingChanges['forcessl'] = $newForceSsl;
            $label = $newForceSsl ? 'enabled' : 'disabled';
            $this->info("Force HTTPS will be {$label}");
        } else {
            unset($this->pendingChanges['forcessl']);
            $this->line("Force HTTPS setting unchanged.");
        }
    }

    /**
     * Modify PHP-FPM profile interactively.
     */
    private function modifyProfile(): void
    {
        $profiles       = PhpProfiles::getAll();
        $currentProfile = $this->pendingChanges['profile'] ?? $this->detectCurrentProfile();

        // Build options with descriptions
        $profileOptions = [];
        foreach ($profiles as $profile) {
            $desc   = PhpProfiles::getDescription($profile);
            $marker = ($profile === $currentProfile) ? ' (current)' : '';
            $profileOptions["{$profile} - {$desc}{$marker}"] = $profile;
        }

        $newProfile = $this->interactiveSelect(
            'HTE-Cli: Select PHP-FPM hardening profile',
            $profileOptions,
            $currentProfile
        );

        $this->pendingChanges['profile'] = $newProfile;
        $this->info("PHP-FPM profile will be set to '{$newProfile}'");

        if ($newProfile === PhpProfiles::HARDENED) {
            $this->warn("WARNING: The 'hardened' profile may break Laravel, Composer, and PHPUnit!");
        }
    }

    /**
     * Modify document root interactively.
     */
    private function modifyDocumentRoot(): void
    {
        $currentDocRoot = $this->pendingChanges['docroot'] ?? $this->vhostConf['docroot'];

        $this->line("Current document root: {$currentDocRoot}");
        $newDocRoot = $this->ask('Enter new document root (leave empty to keep current)', $currentDocRoot);

        if (empty($newDocRoot)) {
            $newDocRoot = $currentDocRoot;
        }

        // Validate the path
        if (!$this->fs->isDir($newDocRoot)) {
            if ($this->confirm("Directory '{$newDocRoot}' does not exist. Create it?", false)) {
                if (!$this->fs->makeDirectory($newDocRoot, 0755, true)) {
                    $this->error("Failed to create directory.");
                    return;
                }
                $this->info("Directory created.");
            } else {
                $this->warn("Document root must be an existing directory.");
                return;
            }
        }

        if ($newDocRoot !== $this->vhostConf['docroot']) {
            $this->pendingChanges['docroot'] = $newDocRoot;
            $this->info("Document root will be changed to {$newDocRoot}");
        } else {
            unset($this->pendingChanges['docroot']);
            $this->line("Document root unchanged.");
        }
    }

    /**
     * Show current configuration.
     */
    private function showCurrentConfiguration(): void
    {
        $this->newLine();
        $this->line("Current configuration:");
        $this->line("  Document Root: {$this->vhostConf['docroot']}");
        $this->line("  PHP Version: {$this->vhostConf['phpver']}");
        $this->line("  SSL: " . ($this->vhostConf['ssl'] ? 'Enabled' : 'Disabled'));
        $this->line("  Force HTTPS: " . ($this->vhostConf['forcedssl'] ? 'Yes' : 'No'));
        $this->line("  Profile: " . $this->detectCurrentProfile());
    }

    /**
     * Show pending changes.
     */
    private function showPendingChanges(): void
    {
        if (empty($this->pendingChanges)) {
            $this->info("No pending changes.");
            return;
        }

        $this->newLine();
        $this->info("Pending changes:");

        if (isset($this->pendingChanges['docroot'])) {
            $this->line("  Document Root: {$this->vhostConf['docroot']} -> {$this->pendingChanges['docroot']}");
        }
        if (isset($this->pendingChanges['phpver'])) {
            $this->line("  PHP Version: {$this->vhostConf['phpver']} -> {$this->pendingChanges['phpver']}");
        }
        if (isset($this->pendingChanges['ssl'])) {
            $from = $this->vhostConf['ssl'] ? 'Enabled' : 'Disabled';
            $to   = $this->pendingChanges['ssl'] ? 'Enabled' : 'Disabled';
            $this->line("  SSL: {$from} -> {$to}");
        }
        if (isset($this->pendingChanges['forcessl'])) {
            $from = $this->vhostConf['forcedssl'] ? 'Yes' : 'No';
            $to   = $this->pendingChanges['forcessl'] ? 'Yes' : 'No';
            $this->line("  Force HTTPS: {$from} -> {$to}");
        }
        if (isset($this->pendingChanges['profile'])) {
            $from = $this->detectCurrentProfile();
            $this->line("  PHP-FPM Profile: {$from} -> {$this->pendingChanges['profile']}");
        }
    }

    /**
     * Detect current PHP-FPM profile from the pool configuration file.
     *
     * Delegates to PhpFpmManager which handles file reading and regex matching.
     *
     * @return string
     */
    private function detectCurrentProfile(): string
    {
        return $this->phpFpm->detectProfile($this->phpFpmConf['conf'] ?? '');
    }

    /**
     * Run in non-interactive mode (using command line options).
     */
    private function runNonInteractiveMode(): void
    {
        $this->info("Current configuration for {$this->domain}:");
        $this->line("   Document Root: {$this->vhostConf['docroot']}");
        $this->line("   PHP Version: {$this->vhostConf['phpver']}");
        $this->line("   SSL: " . ($this->vhostConf['ssl'] ? 'Enabled' : 'Disabled'));
        $this->line("   Force HTTPS: " . ($this->vhostConf['forcedssl'] ? 'Yes' : 'No'));
        $this->newLine();

        // Collect changes from options
        $newPhpVer  = $this->option('phpver');
        $newSsl     = $this->option('ssl') !== null ? answer_to_bool($this->option('ssl')) : null;
        $newForceSsl = $this->option('forcessl') !== null ? answer_to_bool($this->option('forcessl')) : null;
        $newProfile = $this->option('profile');
        $newDocRoot = $this->option('docroot');

        // Validate and add to pending changes
        if ($newPhpVer) {
            $installedVersions = $this->phpFpm->getInstalledVersions();
            if (!in_array($newPhpVer, $installedVersions)) {
                $this->criticalError(
                    "PHP version {$newPhpVer} is not installed. Available: " . implode(', ', $installedVersions)
                );
            }
            if ($newPhpVer !== $this->vhostConf['phpver']) {
                $this->pendingChanges['phpver'] = $newPhpVer;
            }
        }

        if ($newProfile && !PhpProfiles::isValid($newProfile)) {
            $this->criticalError("Invalid profile '{$newProfile}'. Valid: " . implode(', ', PhpProfiles::getAll()));
        }
        if ($newProfile) {
            $this->pendingChanges['profile'] = $newProfile;
        }

        if ($newSsl !== null && $newSsl !== $this->vhostConf['ssl']) {
            $this->pendingChanges['ssl'] = $newSsl;
        }

        if ($newForceSsl !== null && $newForceSsl !== $this->vhostConf['forcedssl']) {
            $this->pendingChanges['forcessl'] = $newForceSsl;
        }

        if ($newDocRoot && $newDocRoot !== $this->vhostConf['docroot']) {
            if (!$this->fs->isDir($newDocRoot)) {
                $this->criticalError("Document root '{$newDocRoot}' does not exist.");
            }
            $this->pendingChanges['docroot'] = $newDocRoot;
        }

        if (empty($this->pendingChanges)) {
            $this->info("No changes specified. Use --interactive for interactive mode, or specify options.");
            return;
        }

        $this->showPendingChanges();
        $this->newLine();

        $this->applyChanges();
    }

    /**
     * Apply all pending changes.
     *
     * Updates Apache and PHP-FPM configurations, regenerates SSL certificates
     * when SSL is newly enabled, and restarts the affected services.
     */
    private function applyChanges(): void
    {
        if (empty($this->pendingChanges)) {
            $this->info("No changes to apply.");
            return;
        }

        // Calculate final values
        $finalDocRoot  = $this->pendingChanges['docroot'] ?? $this->vhostConf['docroot'];
        $finalPhpVer   = $this->pendingChanges['phpver'] ?? $this->vhostConf['phpver'];
        $finalSsl      = $this->pendingChanges['ssl'] ?? $this->vhostConf['ssl'];
        $finalForceSsl = $this->pendingChanges['forcessl'] ?? $this->vhostConf['forcedssl'];
        $finalProfile  = $this->pendingChanges['profile'] ?? $this->detectCurrentProfile();

        // Determine what needs updating
        $apacheNeedsUpdate = isset($this->pendingChanges['docroot']) ||
                             isset($this->pendingChanges['phpver']) ||
                             isset($this->pendingChanges['ssl']) ||
                             isset($this->pendingChanges['forcessl']);

        $phpFpmNeedsUpdate = isset($this->pendingChanges['docroot']) ||
                             isset($this->pendingChanges['phpver']) ||
                             isset($this->pendingChanges['profile']);

        $this->newLine();
        $this->info("Applying changes...");

        // Update Apache configuration
        if ($apacheNeedsUpdate) {
            $this->line("Updating Apache configuration...");

            // Disable old site first
            $vhostConfName = str_replace('.conf', '', basename($this->vhostConf['conf']));
            $this->services->disableSite($vhostConfName);

            // Generate new configuration and write it to disk
            $newApacheConf = $this->apache->getConf($this->domain, $finalDocRoot, $finalPhpVer, $finalSsl, $finalForceSsl);

            if ($this->fs->putContents($this->vhostConf['conf'], $newApacheConf)) {
                $this->info("Apache configuration updated");
            } else {
                $this->criticalError("Failed to update Apache configuration");
            }

            // Re-enable site
            $this->services->enableSite($vhostConfName);

            // Handle SSL certificate if SSL was just enabled
            if ($finalSsl && !$this->vhostConf['ssl']) {
                $this->line("Generating SSL certificate for {$this->domain}...");

                $certScript = $this->sslCert->generateScript($this->domain);
                $tmpFile    = $this->fs->tempFile(sys_get_temp_dir(), "sscert_{$this->domain}");

                $this->fs->putContents($tmpFile, $certScript);
                $this->fs->chmod($tmpFile, 0755);

                list($exitCode, , $error) = $this->process->execute($tmpFile);

                $this->fs->delete($tmpFile);

                if ($exitCode != 0) {
                    $this->warn("SSL certificate generation failed: {$error}");
                } else {
                    $this->info("SSL certificate generated");
                }
            }
        }

        // Update PHP-FPM configuration
        if ($phpFpmNeedsUpdate) {
            $this->line("Updating PHP-FPM configuration...");

            $oldPhpVer   = $this->phpFpmConf['phpver'];
            $oldConfFile = $this->phpFpmConf['conf'];

            // Generate new PHP-FPM configuration
            $newFpmConf = $this->phpFpm->writeConf(
                $this->domain,
                $finalDocRoot,
                $finalPhpVer,
                $this->userContext->getUserName(),
                $this->userContext->getUserGroup(),
                $finalProfile
            );

            if ($newFpmConf) {
                // Delete old config file if PHP version changed
                if ($oldPhpVer !== $finalPhpVer && $this->fs->fileExists($oldConfFile)) {
                    $this->fs->delete($oldConfFile);
                    $this->info("Removed old PHP{$oldPhpVer}-FPM configuration");
                }
                $this->info("PHP{$finalPhpVer}-FPM configuration updated (profile: {$finalProfile})");
            } else {
                $this->warn("Failed to update PHP-FPM configuration");
            }
        }

        // Restart Apache2
        $this->line("Restarting Apache2...");
        list($exitCode, , $error) = $this->services->restartApache();
        if ($exitCode != 0) {
            $this->warn("Failed to restart Apache2: {$error}");
        }

        // Restart PHP-FPM for all affected versions
        if ($phpFpmNeedsUpdate) {
            $versionsToRestart = array_unique([$this->phpFpmConf['phpver'], $finalPhpVer]);
            foreach ($versionsToRestart as $ver) {
                $this->line("Restarting PHP{$ver}-FPM...");
                list($exitCode, , $error) = $this->services->restartPhpFpm($ver);
                if ($exitCode != 0) {
                    $this->warn("Failed to restart PHP{$ver}-FPM: {$error}");
                }
            }
        }

        $this->newLine();
        $this->info("VirtualHost {$this->domain} modified successfully!");
    }
}
