<?php

namespace Mfonte\HteCli\Providers;

use Illuminate\Support\ServiceProvider;
use Mfonte\HteCli\Contracts\ProcessExecutorInterface;
use Mfonte\HteCli\Contracts\FilesystemInterface;
use Mfonte\HteCli\Contracts\ApacheManagerInterface;
use Mfonte\HteCli\Contracts\PhpFpmManagerInterface;
use Mfonte\HteCli\Contracts\HostsManagerInterface;
use Mfonte\HteCli\Contracts\SslCertManagerInterface;
use Mfonte\HteCli\Contracts\ServiceManagerInterface;
use Mfonte\HteCli\Contracts\EnvironmentCheckerInterface;
use Mfonte\HteCli\Contracts\UserContextInterface;
use Mfonte\HteCli\Services\SystemProcessExecutor;
use Mfonte\HteCli\Services\SystemFilesystem;
use Mfonte\HteCli\Services\ApacheManager;
use Mfonte\HteCli\Services\PhpFpmManager;
use Mfonte\HteCli\Services\HostsManager;
use Mfonte\HteCli\Services\SslCertManager;
use Mfonte\HteCli\Services\ServiceManager;
use Mfonte\HteCli\Services\EnvironmentChecker;
use Mfonte\HteCli\Services\UserContext;

/**
 * Registers all HTE-CLI service bindings in the Laravel container.
 *
 * Every service is bound as a singleton so that the same instance is reused
 * throughout a single CLI invocation. Production defaults (system paths)
 * are used; tests override these by binding their own implementations
 * (InMemoryFilesystem, FakeProcessExecutor) before resolving commands.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register all service bindings.
     *
     * @return void
     */
    public function register()
    {
        // Low-level infrastructure: process executor and filesystem
        $this->app->singleton(ProcessExecutorInterface::class, function () {
            return new SystemProcessExecutor();
        });

        $this->app->singleton(FilesystemInterface::class, function () {
            return new SystemFilesystem();
        });

        // Environment and user context
        $this->app->singleton(EnvironmentCheckerInterface::class, function ($app) {
            return new EnvironmentChecker($app->make(ProcessExecutorInterface::class));
        });

        $this->app->singleton(UserContextInterface::class, function () {
            return new UserContext();
        });

        // Domain services
        $this->app->singleton(ApacheManagerInterface::class, function ($app) {
            return new ApacheManager($app->make(FilesystemInterface::class));
        });

        $this->app->singleton(PhpFpmManagerInterface::class, function ($app) {
            return new PhpFpmManager($app->make(FilesystemInterface::class));
        });

        $this->app->singleton(HostsManagerInterface::class, function ($app) {
            return new HostsManager(
                $app->make(FilesystemInterface::class),
                $app->make(ProcessExecutorInterface::class)
            );
        });

        $this->app->singleton(SslCertManagerInterface::class, function ($app) {
            return new SslCertManager($app->make(FilesystemInterface::class));
        });

        $this->app->singleton(ServiceManagerInterface::class, function ($app) {
            return new ServiceManager($app->make(ProcessExecutorInterface::class));
        });
    }
}
