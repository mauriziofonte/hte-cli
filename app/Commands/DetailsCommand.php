<?php

namespace Mfonte\HteCli\Commands;

use Mfonte\HteCli\CommandWrapper;
use Mfonte\HteCli\Logic\Daemons\Apache2;
use Mfonte\HteCli\Logic\Preprocessors\Php;

class DetailsCommand extends CommandWrapper
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'details';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Lists all details of available LAMP Test Environments';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // pre-flight stuff: OS version, enabled functions, and root privileges
        $this->preRun();

        // get a list of the current vhosts so that we can show it to the user and use it for validation
        $currentVhosts = collect(Apache2::getVhostsList())->map(function ($vhost) {
            return [
                'index' => $vhost['index'],
                'domain' => $vhost['domain'] . PHP_EOL . " > {$vhost['docroot']}",
                'phpver' => $vhost['phpver'],
                'ssl' => $vhost['ssl'],
                'forcedssl' => $vhost['forcedssl'],
                'enabled' => $vhost['enabled'],
            ];
        })->toArray();
        $validDomains = collect(Apache2::getVhostsList())->map(function ($vhost) {
            return $vhost['domain'];
        })->toArray();

        $count = count($currentVhosts);
        $this->info("⚙️ VHosts Count: {$count}");

        $this->table(['Index', 'Domain / DocRoot', 'PHP Version', 'SSL?', 'Forced SSL?', 'Enabled'], $currentVhosts);

        // ask for a domain to show the PHP-FPM details
        $domain = $this->keepAsking('📋 Optionally type in a domain name for the PHP-FPM details', "", function ($answer) use ($validDomains) {
            return in_array($answer, $validDomains);
        });

        $phpFpmConf = Php::getConfByDomain($domain);

        // dump the contents of the PHP-FPM configuration file
        $this->info("📋 PHP-FPM Configuration for {$domain}:");
        $this->info("🔍 PHP-FPM Version: {$phpFpmConf['phpver']}");
        $this->info("🔍 PHP-FPM Config File: {$phpFpmConf['conf']}");
        $this->warn(file_get_contents($phpFpmConf['conf']));
    }
}
