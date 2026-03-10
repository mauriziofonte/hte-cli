<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Doubles\InMemoryFilesystem;
use Mfonte\HteCli\Services\PhpFpmManager;
use Mfonte\HteCli\Logic\Preprocessors\PhpProfiles;

/**
 * Unit tests for PhpFpmManager.
 *
 * All filesystem operations go through InMemoryFilesystem so no real
 * /etc/php directory is required.
 */
class PhpFpmManagerTest extends TestCase
{
    /** @var InMemoryFilesystem */
    private $fs;

    /** @var PhpFpmManager */
    private $phpFpm;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new InMemoryFilesystem([], [
            '/test/php/7.4/fpm/pool.d',
            '/test/php/8.0/fpm/pool.d',
            '/test/php/8.1/fpm/pool.d',
            '/test/php/8.4/fpm/pool.d',
        ]);
        $this->phpFpm = new PhpFpmManager($this->fs, '/test/php');
    }

    // -------------------------------------------------------------------------
    // getInstalledVersions()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testGetInstalledVersionsDetectsVersionDirs(): void
    {
        $versions = $this->phpFpm->getInstalledVersions();

        $this->assertSame(['7.4', '8.0', '8.1', '8.4'], $versions);
    }

    /**
     * @test
     * @return void
     */
    public function testGetInstalledVersionsReturnsEmptyWhenNoneInstalled(): void
    {
        $emptyFs = new InMemoryFilesystem([], ['/test/php']);
        $manager = new PhpFpmManager($emptyFs, '/test/php');

        $this->assertSame([], $manager->getInstalledVersions());
    }

    // -------------------------------------------------------------------------
    // versionExists()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testVersionExistsReturnsTrueForInstalledVersion(): void
    {
        $this->assertTrue($this->phpFpm->versionExists('8.1'));
    }

    /**
     * @test
     * @return void
     */
    public function testVersionExistsReturnsFalseForMissingVersion(): void
    {
        $this->assertFalse($this->phpFpm->versionExists('5.6'));
    }

    // -------------------------------------------------------------------------
    // getConf()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testGetConfGeneratesValidPoolConfig(): void
    {
        $conf = $this->phpFpm->getConf('example.test', '/var/www/example', '8.1', 'www-data', 'www-data');

        $this->assertStringContainsString('[example.test]', $conf);
        $this->assertStringContainsString('user = www-data', $conf);
        $this->assertStringContainsString('group = www-data', $conf);
        $this->assertStringContainsString('listen = /var/run/php/php8.1-fpm-example.test.sock', $conf);
        $this->assertStringContainsString('pm = dynamic', $conf);
        $this->assertStringContainsString('pm.max_children', $conf);
        $this->assertStringContainsString('pm.start_servers', $conf);
    }

    /**
     * @test
     * @return void
     */
    public function testGetConfWithDifferentPhpVersions(): void
    {
        $versions = ['7.4', '8.0', '8.1', '8.4'];

        foreach ($versions as $version) {
            $conf = $this->phpFpm->getConf('ver.test', '/var/www/ver', $version, 'www-data', 'www-data');
            $this->assertStringContainsString(
                "listen = /var/run/php/php{$version}-fpm-ver.test.sock",
                $conf,
                "Socket path missing for PHP {$version}"
            );
        }
    }

    /**
     * @test
     * @return void
     */
    public function testGetConfIncludesSecuritySettings(): void
    {
        $conf = $this->phpFpm->getConf('sec.test', '/var/www/sec', '8.1', 'www-data', 'www-data');

        $this->assertStringContainsString('disable_functions', $conf);
        $this->assertStringContainsString('allow_url_fopen', $conf);
    }

    /**
     * @test
     * @return void
     */
    public function testGetConfIncludesResourceLimits(): void
    {
        $conf = $this->phpFpm->getConf('limits.test', '/var/www/limits', '8.1', 'www-data', 'www-data');

        $this->assertStringContainsString('post_max_size', $conf);
        $this->assertStringContainsString('upload_max_filesize', $conf);
        $this->assertStringContainsString('memory_limit', $conf);
    }

    /**
     * @test
     * @return void
     */
    public function testGetConfIncludesLogging(): void
    {
        $conf = $this->phpFpm->getConf('log.test', '/var/www/log', '8.1', 'www-data', 'www-data');

        $this->assertStringContainsString('slowlog', $conf);
        $this->assertStringContainsString('error_log', $conf);
    }

    // -------------------------------------------------------------------------
    // writeConf()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testWriteConfCreatesFile(): void
    {
        $this->phpFpm->writeConf('write.test', '/var/www/write', '8.1', 'www-data', 'www-data');

        $expectedPath = '/test/php/8.1/fpm/pool.d/write.test.conf';
        $this->assertTrue($this->fs->fileExists($expectedPath));
    }

    /**
     * @test
     * @return void
     */
    public function testWriteConfReturnsPath(): void
    {
        $path = $this->phpFpm->writeConf('ret.test', '/var/www/ret', '8.4', 'www-data', 'www-data');

        $this->assertSame('/test/php/8.4/fpm/pool.d/ret.test.conf', $path);
    }

    // -------------------------------------------------------------------------
    // getConfByDomain()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testGetConfByDomainFindsConfig(): void
    {
        // Pre-populate a pool file containing the [domain] section header
        $poolContent = "[found.test]\nuser = www-data\ngroup = www-data\n";
        $this->fs->putContents('/test/php/8.1/fpm/pool.d/found.test.conf', $poolContent);

        $result = $this->phpFpm->getConfByDomain('found.test');

        $this->assertIsArray($result);
        $this->assertSame('/test/php/8.1/fpm/pool.d/found.test.conf', $result['conf']);
        $this->assertSame('found.test.conf', $result['name']);
        $this->assertSame('8.1', $result['phpver']);
        $this->assertSame('found.test', $result['domain']);
    }

    /**
     * @test
     * @return void
     */
    public function testGetConfByDomainReturnsNullWhenNotFound(): void
    {
        $result = $this->phpFpm->getConfByDomain('nobody.test');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // detectProfile()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testDetectProfileReturnsDevAsDefault(): void
    {
        // File exists but has no Profile marker
        $this->fs->putContents('/test/php/8.1/fpm/pool.d/noprofile.conf', "[noprofile.test]\nuser = www-data\n");

        $profile = $this->phpFpm->detectProfile('/test/php/8.1/fpm/pool.d/noprofile.conf');

        $this->assertSame(PhpProfiles::DEV, $profile);
    }

    /**
     * @test
     * @return void
     */
    public function testDetectProfileDetectsProfileMarker(): void
    {
        $content = "[staging.test]\n; Profile: staging\nuser = www-data\n";
        $this->fs->putContents('/test/php/8.1/fpm/pool.d/staging.test.conf', $content);

        $profile = $this->phpFpm->detectProfile('/test/php/8.1/fpm/pool.d/staging.test.conf');

        $this->assertSame('staging', $profile);
    }

    /**
     * @test
     * @return void
     */
    public function testDetectProfileReturnsDevForNonexistentFile(): void
    {
        $profile = $this->phpFpm->detectProfile('/test/php/8.1/fpm/pool.d/does-not-exist.conf');

        $this->assertSame(PhpProfiles::DEV, $profile);
    }

    // -------------------------------------------------------------------------
    // getPotentialVersions()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testGetPotentialVersionsContainsExpected(): void
    {
        $versions = $this->phpFpm->getPotentialVersions();

        $this->assertIsArray($versions);
        $this->assertContains('7.4', $versions);
        $this->assertContains('8.0', $versions);
        $this->assertContains('8.1', $versions);
        $this->assertContains('8.4', $versions);
    }
}
