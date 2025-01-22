<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Logic\Daemons\Apache2;
use Mfonte\HteCli\Logic\Preprocessors\Php;

class RemoveCommand extends CommandWrapper
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'remove
    {--domain= : The Domain name you want to remove from the LAMP Test Environment}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Delete a LAMP Test Environment by its Domain Name';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // pre-flight stuff: OS version, enabled functions, and root privileges
        $this->preRun();

        // collect command line options or nullify if not provided (ask the user for them)
        $domain = $this->option('domain') ? $this->option('domain') : null;

        // get a list of the current vhosts so that we can show it to the user and use it for validation
        $currentVhosts = Apache2::getVhostsList();
        $currentVhosts = collect($currentVhosts)->map(function ($vhost) {
            return [
                'index' => $vhost['index'],
                'domain' => $vhost['domain'],
                'enabled' => $vhost['enabled'],
            ];
        })->toArray();

        if (count($currentVhosts) === 0) {
            $this->warn('🔴 No VirtualHosts found: nothing to remove. Create a VirtualHost first.');
            $this->line('   More info at https://github.com/mauriziofonte/hte-cli');
            return;
        }

        if (empty($domain) || !validate_domain($domain)) {
            // show the table with the current vhosts
            $this->table(['Index', 'Domain', 'Enabled'], $currentVhosts);

            // ask for the domain
            $domain = $this->keepAsking('Enter the Domain Name you want to remove', "", function ($answer) use ($currentVhosts) {
                return in_array($answer, array_column($currentVhosts, 'domain'));
            });
        }

        $this->line("⏳ Deleting {$domain}...");

        $toBeUnlinked = [];
        
        // apache
        $vhostConf = Apache2::getConfByDomain($domain);
        if (empty($vhostConf)) {
            $this->warn("🔴 No VirtualHost found for {$domain}");
        } else {
            $toBeUnlinked[] = $vhostConf['conf'];
            $toBeUnlinked[] = $vhostConf['sslcertfile'];
            $toBeUnlinked[] = $vhostConf['sslkeyfile'];

            // if the domain is enabled, call a2dissite on it
            if ($vhostConf['enabled']) {
                $vhostConfName = str_replace('.conf', '', basename($vhostConf['conf']));
                $this->line("⏳ Disabling {$domain} on config {$vhostConfName}...");
                list($exitCode, $output, $error) = proc_exec("a2dissite {$vhostConfName}");
                if ($exitCode === 0) {
                    $this->info("✅ {$domain} disabled successfully");
                } else {
                    $this->warn("🔴 {$domain} could not be disabled: {$error}");
                }
            }
        }

        // php-fpm
        $phpFpmConf = Php::getConfByDomain($domain);
        $phpVersion = null;
        if (empty($phpFpmConf)) {
            $this->warn("🔴 No PHP-FPM configuration found for {$domain}");
        } else {
            $phpVersion = $phpFpmConf['phpver'];
            $toBeUnlinked[] = $phpFpmConf['conf'];
        }

        // clean up
        $toBeUnlinked = array_filter($toBeUnlinked);

        // unlink
        array_map(function ($file) {
            if (file_exists($file)) {
                unlink($file);
                if (!is_file($file)) {
                    $this->info("🗑️ {$file} deleted");
                } else {
                    $this->warn("🔴 {$file} could not be deleted");
                }
            }
        }, $toBeUnlinked);

        // restart Apache2
        $this->line("⏳ Restarting Apache2...");
        list($exitCode, $output, $error) = proc_exec('systemctl restart apache2.service');
        if ($exitCode === 0) {
            $this->info("✅ Apache2 restarted successfully");
        } else {
            $this->warn("🔴 Apache2 could not be restarted: {$error}");
        }

        // restart PHP{$phpver}-FPM
        if ($phpVersion) {
            $this->line("⏳ Restarting PHP{$phpVersion}-FPM...");
            list($exitCode, $output, $error) = proc_exec("systemctl restart php{$phpVersion}-fpm.service");
            if ($exitCode === 0) {
                $this->info("✅ PHP{$phpVersion}-FPM restarted successfully");
            } else {
                $this->warn("🔴 PHP{$phpVersion}-FPM could not be restarted: {$error}");
            }
        }

        // completed!
        $this->info("✅ VirtualHost {$domain} deleted successfully!");
    }
}
