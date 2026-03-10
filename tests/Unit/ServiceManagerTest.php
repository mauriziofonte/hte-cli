<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Doubles\FakeProcessExecutor;
use Mfonte\HteCli\Services\ServiceManager;

/**
 * Unit tests for ServiceManager.
 *
 * Verifies that every service operation dispatches the correct command string
 * to the underlying ProcessExecutorInterface, using FakeProcessExecutor to
 * record and inspect all calls without touching the real system.
 */
class ServiceManagerTest extends TestCase
{
    /** @var FakeProcessExecutor */
    private $process;

    /** @var ServiceManager */
    private $manager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->process = new FakeProcessExecutor();
        $this->manager = new ServiceManager($this->process);
    }

    // -------------------------------------------------------------------------
    // restartApache()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testRestartApacheCallsSystemctl(): void
    {
        $this->manager->restartApache();

        $this->assertTrue(
            $this->process->wasExecuted('systemctl restart apache2.service'),
            'Expected systemctl restart apache2.service to be executed'
        );
    }

    /**
     * @test
     * @return void
     */
    public function testRestartApacheReturnsExitCodeAndOutput(): void
    {
        $this->process->addResponse('apache2.service', 0, 'OK', '');

        $result = $this->manager->restartApache();

        $this->assertIsArray($result);
        $this->assertSame(0, $result[0]);
        $this->assertSame('OK', $result[1]);
    }

    // -------------------------------------------------------------------------
    // restartPhpFpm()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testRestartPhpFpmCallsSystemctl(): void
    {
        $this->manager->restartPhpFpm('8.1');

        $this->assertTrue(
            $this->process->wasExecuted("systemctl restart 'php8.1-fpm.service'"),
            'Expected systemctl restart php8.1-fpm.service to be executed'
        );
    }

    /**
     * @test
     * @return void
     */
    public function testRestartPhpFpmWithDifferentVersions(): void
    {
        $versions = ['7.4', '8.0', '8.1', '8.2', '8.4'];

        foreach ($versions as $version) {
            $this->process->reset();
            $this->manager->restartPhpFpm($version);

            $this->assertTrue(
                $this->process->wasExecuted("systemctl restart 'php{$version}-fpm.service'"),
                "Expected systemctl restart php{$version}-fpm.service to be executed"
            );
        }
    }

    // -------------------------------------------------------------------------
    // enableSite()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testEnableSiteCallsA2ensite(): void
    {
        $this->manager->enableSite('example.test.conf');

        $this->assertTrue(
            $this->process->wasExecuted("a2ensite 'example.test.conf'"),
            'Expected a2ensite example.test.conf to be executed'
        );
    }

    // -------------------------------------------------------------------------
    // disableSite()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testDisableSiteCallsA2dissite(): void
    {
        $this->manager->disableSite('example.test.conf');

        $this->assertTrue(
            $this->process->wasExecuted("a2dissite 'example.test.conf'"),
            'Expected a2dissite example.test.conf to be executed'
        );
    }

    // -------------------------------------------------------------------------
    // Shell injection prevention
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testEnableSiteEscapesConfigName(): void
    {
        $this->manager->enableSite('evil; rm -rf /');

        $commands = $this->process->getExecutedCommands();
        $lastCommand = end($commands);

        // escapeshellarg wraps the argument in single quotes and escapes internal quotes
        $this->assertStringContainsString("'evil; rm -rf /'", $lastCommand);
        $this->assertStringNotContainsString('a2ensite evil; rm', $lastCommand);
    }

    /**
     * @test
     * @return void
     */
    public function testRestartPhpFpmEscapesVersion(): void
    {
        $this->manager->restartPhpFpm('8.1; whoami');

        $commands = $this->process->getExecutedCommands();
        $lastCommand = end($commands);

        // The full service name should be escaped as one argument
        $this->assertStringContainsString("'php8.1; whoami-fpm.service'", $lastCommand);
    }
}
