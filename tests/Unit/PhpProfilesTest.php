<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mfonte\HteCli\Logic\Preprocessors\PhpProfiles;

class PhpProfilesTest extends TestCase
{
    public function testGetAllReturnsAllProfiles(): void
    {
        $profiles = PhpProfiles::getAll();

        $this->assertCount(3, $profiles);
        $this->assertContains(PhpProfiles::DEV, $profiles);
        $this->assertContains(PhpProfiles::STAGING, $profiles);
        $this->assertContains(PhpProfiles::HARDENED, $profiles);
    }

    public function testIsValidAcceptsValidProfiles(): void
    {
        $this->assertTrue(PhpProfiles::isValid(PhpProfiles::DEV));
        $this->assertTrue(PhpProfiles::isValid(PhpProfiles::STAGING));
        $this->assertTrue(PhpProfiles::isValid(PhpProfiles::HARDENED));
    }

    public function testIsValidRejectsInvalidProfiles(): void
    {
        $this->assertFalse(PhpProfiles::isValid('invalid'));
        $this->assertFalse(PhpProfiles::isValid('production'));
        $this->assertFalse(PhpProfiles::isValid(''));
    }

    public function testGetDescriptionReturnsDescriptions(): void
    {
        $devDesc = PhpProfiles::getDescription(PhpProfiles::DEV);
        $stagingDesc = PhpProfiles::getDescription(PhpProfiles::STAGING);
        $hardenedDesc = PhpProfiles::getDescription(PhpProfiles::HARDENED);

        $this->assertStringContainsString('Development', $devDesc);
        $this->assertStringContainsString('Staging', $stagingDesc);
        $this->assertStringContainsString('Hardened', $hardenedDesc);
        $this->assertStringContainsString('WARNING', $hardenedDesc);
    }

    public function testDevProfileHasMinimalDisabledFunctions(): void
    {
        $disabled = PhpProfiles::getDisabledFunctions(PhpProfiles::DEV);

        // Dev should have only base apache functions disabled
        $this->assertStringContainsString('apache_child_terminate', $disabled);
        $this->assertStringContainsString('apache_get_modules', $disabled);

        // Dev should NOT have exec/shell_exec disabled
        $this->assertStringNotContainsString('exec', $disabled);
        $this->assertStringNotContainsString('shell_exec', $disabled);
        $this->assertStringNotContainsString('proc_open', $disabled);
    }

    public function testStagingProfileHasModerateDisabledFunctions(): void
    {
        $disabled = PhpProfiles::getDisabledFunctions(PhpProfiles::STAGING);

        // Staging should have staging-specific functions disabled
        $this->assertStringContainsString('phpinfo', $disabled);
        $this->assertStringContainsString('highlight_file', $disabled);
        $this->assertStringContainsString('show_source', $disabled);

        // Staging should NOT have exec/shell_exec disabled
        $this->assertStringNotContainsString(',exec,', $disabled);
        $this->assertStringNotContainsString('shell_exec', $disabled);
    }

    public function testHardenedProfileHasMaximumDisabledFunctions(): void
    {
        $disabled = PhpProfiles::getDisabledFunctions(PhpProfiles::HARDENED);

        // Hardened should have execution functions disabled
        $this->assertStringContainsString('exec', $disabled);
        $this->assertStringContainsString('shell_exec', $disabled);
        $this->assertStringContainsString('proc_open', $disabled);
        $this->assertStringContainsString('system', $disabled);
        $this->assertStringContainsString('passthru', $disabled);
    }

    public function testDevProfileHasNoOpenBasedir(): void
    {
        $config = PhpProfiles::getAdditionalConfig(PhpProfiles::DEV, '/var/www');

        $this->assertArrayNotHasKey('open_basedir', $config);
    }

    public function testStagingProfileHasOpenBasedir(): void
    {
        $config = PhpProfiles::getAdditionalConfig(PhpProfiles::STAGING, '/var/www');

        $this->assertArrayHasKey('open_basedir', $config);
        $this->assertStringContainsString('/var/www', $config['open_basedir']);
        $this->assertStringContainsString('/tmp', $config['open_basedir']);
    }

    public function testHardenedProfileHasReducedLimits(): void
    {
        $config = PhpProfiles::getAdditionalConfig(PhpProfiles::HARDENED, '/var/www');

        $this->assertArrayHasKey('memory_limit', $config);
        $this->assertEquals('256M', $config['memory_limit']);
        $this->assertEquals('32M', $config['post_max_size']);
        $this->assertEquals('32M', $config['upload_max_filesize']);
    }

    public function testGetFullConfigGeneratesValidConfig(): void
    {
        $config = PhpProfiles::getFullConfig(
            PhpProfiles::DEV,
            'test.local',
            '/var/www',
            '8.4',
            'webuser',
            'webgroup'
        );

        $this->assertStringContainsString('[test.local]', $config);
        $this->assertStringContainsString('user = webuser', $config);
        $this->assertStringContainsString('group = webgroup', $config);
        $this->assertStringContainsString('php8.4-fpm-test.local.sock', $config);
        $this->assertStringContainsString('; Profile: dev', $config);
    }

    public function testStagingConfigIncludesMaxRequests(): void
    {
        $config = PhpProfiles::getFullConfig(
            PhpProfiles::STAGING,
            'staging.test',
            '/var/www',
            '8.4',
            'user',
            'group'
        );

        $this->assertStringContainsString('pm.max_requests = 500', $config);
        $this->assertStringContainsString('; Profile: staging', $config);
    }

    public function testHardenedConfigIncludesAllRestrictions(): void
    {
        $config = PhpProfiles::getFullConfig(
            PhpProfiles::HARDENED,
            'hardened.test',
            '/var/www',
            '8.4',
            'user',
            'group'
        );

        $this->assertStringContainsString('; Profile: hardened', $config);
        $this->assertStringContainsString('php_admin_value[memory_limit] = 256M', $config);
        $this->assertStringContainsString('php_admin_flag[session.cookie_httponly] = on', $config);
        $this->assertStringContainsString('exec', $config); // In disabled functions
    }
}
