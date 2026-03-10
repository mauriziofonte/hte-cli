<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Doubles\InMemoryFilesystem;
use Mfonte\HteCli\Services\SslCertManager;

/**
 * Tests for SslCertManager service.
 *
 * Verifies SSL certificate script generation, directory creation,
 * domain validation, and edge cases.
 */
class SslCertManagerTest extends TestCase
{
    /** @var InMemoryFilesystem */
    private $fs;

    /** @var SslCertManager */
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new InMemoryFilesystem([], ['/test']);
        $this->manager = new SslCertManager($this->fs, '/test/certs');
    }

    // -------------------------------------------------------------------------
    // generateScript() — happy path
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function testGenerateScriptReturnsValidBashScript(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('#!/usr/bin/env bash', $script);
        $this->assertStringContainsString('openssl req', $script);
        $this->assertStringContainsString('exit 0', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptContainsDomain(): void
    {
        $script = $this->manager->generateScript('myapp.test');

        $this->assertStringContainsString('CN = myapp.test', $script);
        $this->assertStringContainsString('DNS.1 = *.myapp.test', $script);
        $this->assertStringContainsString('DNS.2 = myapp.test', $script);
        $this->assertStringContainsString('webmaster@myapp.test', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptContainsCertsDir(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('/test/certs/example.test.key', $script);
        $this->assertStringContainsString('/test/certs/example.test.crt', $script);
        $this->assertStringContainsString('/test/certs/example.test.info', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptUsesCustomDays(): void
    {
        $script = $this->manager->generateScript('example.test', 365);

        $this->assertStringContainsString('-days 365', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptUsesDefaultDays(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('-days 10950', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptCreatesCertsDirectory(): void
    {
        $this->assertFalse($this->fs->isDir('/test/certs'));

        $this->manager->generateScript('example.test');

        $this->assertTrue($this->fs->isDir('/test/certs'));
    }

    /**
     * @test
     */
    public function testGenerateScriptDoesNotRecreateCertsDirectory(): void
    {
        $this->fs->makeDirectory('/test/certs', 0755, true);

        // Should not throw or fail
        $script = $this->manager->generateScript('example.test');

        $this->assertNotEmpty($script);
        $this->assertTrue($this->fs->isDir('/test/certs'));
    }

    /**
     * @test
     */
    public function testGenerateScriptIncludesRootCheck(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('id -u', $script);
        $this->assertStringContainsString('must be run as root', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptIncludesOpensslCheck(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('command -v openssl', $script);
        $this->assertStringContainsString('openssl binary is required', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptProtectsPrivateKey(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('chmod 400', $script);
    }

    /**
     * @test
     */
    public function testGenerateScriptCleansTempFiles(): void
    {
        $script = $this->manager->generateScript('example.test');

        $this->assertStringContainsString('rm -f $CONFIG_FILE', $script);
        $this->assertStringContainsString('unset PASSPHRASE', $script);
    }

    // -------------------------------------------------------------------------
    // generateScript() — domain validation (shell injection prevention)
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function testGenerateScriptRejectsDomainWithShellMetachars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid domain name');

        $this->manager->generateScript('test; rm -rf /');
    }

    /**
     * @test
     */
    public function testGenerateScriptRejectsDomainWithBackticks(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->generateScript('test`whoami`.com');
    }

    /**
     * @test
     */
    public function testGenerateScriptRejectsDomainWithDollarSign(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->generateScript('$(whoami).test');
    }

    /**
     * @test
     */
    public function testGenerateScriptRejectsDomainWithSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->generateScript('test domain.com');
    }

    /**
     * @test
     */
    public function testGenerateScriptRejectsDomainWithSlashes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->generateScript('test/../../etc/passwd');
    }

    /**
     * @test
     */
    public function testGenerateScriptAcceptsValidDomains(): void
    {
        // These should all work without exceptions
        $domains = [
            'example.test',
            'my-app.local',
            'sub.domain.test',
            '192.168.1.100',
            'APP.TEST',
            'a_b.test',
        ];

        foreach ($domains as $domain) {
            $script = $this->manager->generateScript($domain);
            $this->assertStringContainsString($domain, $script, "Domain {$domain} should be accepted");
        }
    }

    // -------------------------------------------------------------------------
    // getCertsDir()
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function testGetCertsDirReturnsConfiguredPath(): void
    {
        $this->assertSame('/test/certs', $this->manager->getCertsDir());
    }

    /**
     * @test
     */
    public function testGetCertsDirReturnsDefaultPath(): void
    {
        $manager = new SslCertManager($this->fs);

        $this->assertSame('/etc/apache2/certs-selfsigned', $manager->getCertsDir());
    }
}
