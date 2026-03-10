<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mfonte\HteCli\Services\SystemProcessExecutor;

/**
 * Integration tests for SystemProcessExecutor.
 *
 * Exercises the real proc_open implementation with safe shell commands.
 */
class SystemProcessExecutorTest extends TestCase
{
    /** @var SystemProcessExecutor */
    private $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new SystemProcessExecutor();
    }

    public function testExecuteReturnsExitCodeStdoutStderr(): void
    {
        list($exitCode, $stdout, $stderr) = $this->executor->execute('echo hello');

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('hello', $stdout);
        $this->assertEquals('', $stderr);
    }

    public function testExecuteReturnsNonZeroExitCode(): void
    {
        list($exitCode, $stdout, $stderr) = $this->executor->execute('false');

        $this->assertNotEquals(0, $exitCode);
    }

    public function testExecuteCapturesStderr(): void
    {
        list($exitCode, $stdout, $stderr) = $this->executor->execute('echo error_msg >&2');

        $this->assertEquals('error_msg', $stderr);
    }

    public function testExecuteTrimsOutput(): void
    {
        list($exitCode, $stdout, $stderr) = $this->executor->execute('echo "  padded  "');

        $this->assertEquals('padded', $stdout);
    }

    public function testExecuteHandlesMultiLineOutput(): void
    {
        list($exitCode, $stdout, $stderr) = $this->executor->execute('echo -e "line1\nline2\nline3"');

        $this->assertStringContainsString('line1', $stdout);
        $this->assertStringContainsString('line3', $stdout);
    }

    public function testExecuteReturnsArrayWithThreeElements(): void
    {
        $result = $this->executor->execute('true');

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }
}
