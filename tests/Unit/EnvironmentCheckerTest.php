<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Doubles\FakeProcessExecutor;
use Mfonte\HteCli\Services\EnvironmentChecker;

/**
 * Tests for EnvironmentChecker service.
 *
 * Uses FakeProcessExecutor to simulate binary availability checks.
 */
class EnvironmentCheckerTest extends TestCase
{
    /** @var FakeProcessExecutor */
    private $process;

    /** @var EnvironmentChecker */
    private $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->process = new FakeProcessExecutor();
        $this->checker = new EnvironmentChecker($this->process);
    }

    public function testIsSupportedOsReturnsTrueOnLinux(): void
    {
        // We're running on Linux in tests
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('This test requires a non-Windows OS.');
        }

        $this->assertTrue($this->checker->isSupportedOs());
    }

    public function testHasShellReturnsTrueWhenShellSet(): void
    {
        // In a test environment, SHELL is typically set
        if (!array_key_exists('SHELL', $_SERVER)) {
            $this->markTestSkipped('No SHELL in $_SERVER.');
        }

        $this->assertTrue($this->checker->hasShell());
    }

    public function testHasBinaryReturnsTrueWhenCommandExists(): void
    {
        $this->process->addResponse('command -v', 0, '/usr/bin/apache2');

        $this->assertTrue($this->checker->hasBinary('apache2'));
    }

    public function testHasBinaryReturnsFalseWhenCommandNotFound(): void
    {
        $this->process->addResponse('command -v', 1, '');

        $this->assertFalse($this->checker->hasBinary('nonexistent'));
    }

    public function testHasFunctionReturnsTrueForExistingFunction(): void
    {
        $this->assertTrue($this->checker->hasFunction('strlen'));
    }

    public function testHasFunctionReturnsFalseForNonExistingFunction(): void
    {
        $this->assertFalse($this->checker->hasFunction('nonexistent_function_xyz'));
    }

    public function testGetRequiredFunctionsReturnsArray(): void
    {
        $functions = $this->checker->getRequiredFunctions();

        $this->assertIsArray($functions);
        $this->assertContains('exec', $functions);
        $this->assertContains('posix_getuid', $functions);
        $this->assertContains('posix_getpwuid', $functions);
        $this->assertContains('posix_getgrgid', $functions);
    }

    public function testGetRequiredBinariesReturnsArray(): void
    {
        $binaries = $this->checker->getRequiredBinaries();

        $this->assertIsArray($binaries);
        $this->assertContains('apache2', $binaries);
        $this->assertContains('php', $binaries);
    }

    public function testHasBinaryExecutesCommandWithEscapedArg(): void
    {
        $this->process->addResponse('command -v', 0, '/usr/bin/test');

        $this->checker->hasBinary('test');

        $commands = $this->process->getExecutedCommands();
        $this->assertCount(1, $commands);
        // Verify the binary name was passed through escapeshellarg
        $this->assertStringContainsString("'test'", $commands[0]);
    }
}
