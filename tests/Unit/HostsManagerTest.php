<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Doubles\InMemoryFilesystem;
use Tests\Doubles\FakeProcessExecutor;
use Mfonte\HteCli\Services\HostsManager;
use Mfonte\HteCli\Contracts\HostsManagerInterface;

/**
 * Unit tests for HostsManager.
 *
 * Exercises all methods of HostsManager through InMemoryFilesystem and
 * FakeProcessExecutor — no real filesystem or process interaction.
 */
class HostsManagerTest extends TestCase
{
    /** @var InMemoryFilesystem */
    private $fs;

    /** @var FakeProcessExecutor */
    private $process;

    /** @var HostsManager */
    private $hosts;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $initialContent = "127.0.0.1    localhost\n::1          localhost\n127.0.0.1    existing.manual.test\n";
        $this->fs = new InMemoryFilesystem(['/test/hosts' => $initialContent], ['/test']);
        $this->process = new FakeProcessExecutor();
        $this->hosts = new HostsManager($this->fs, $this->process, '/test/hosts');
    }

    // -------------------------------------------------------------------------
    // exists()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testExistsReturnsTrueForExistingDomain(): void
    {
        $this->assertTrue($this->hosts->exists('localhost'));
    }

    /**
     * @test
     * @return void
     */
    public function testExistsReturnsFalseForNonExistingDomain(): void
    {
        $this->assertFalse($this->hosts->exists('nonexistent.test'));
    }

    // -------------------------------------------------------------------------
    // add()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testAddDomainAppendsEntry(): void
    {
        $result = $this->hosts->add('new.test');

        $this->assertTrue($result);

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringContainsString('new.test', $content);
        $this->assertStringContainsString(HostsManagerInterface::MARKER, $content);
    }

    /**
     * @test
     * @return void
     */
    public function testAddDomainReturnsFalseForDuplicate(): void
    {
        $this->hosts->add('dup.test');
        $result = $this->hosts->add('dup.test');

        $this->assertFalse($result);
    }

    /**
     * @test
     * @return void
     */
    public function testAddDomainFlushesCache(): void
    {
        $this->hosts->add('flush.test');

        // On Linux the manager calls systemd-resolve and/or nscd.
        // On macOS it calls dscacheutil / killall mDNSResponder.
        // Either way at least one command must have been dispatched.
        $commands = $this->process->getExecutedCommands();
        $this->assertNotEmpty($commands, 'Expected DNS flush command(s) to be executed after add()');
    }

    // -------------------------------------------------------------------------
    // remove()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testRemoveDomainRemovesHteCliEntry(): void
    {
        $this->hosts->add('remove.test');
        $result = $this->hosts->remove('remove.test');

        $this->assertTrue($result);

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringNotContainsString('127.0.0.1    remove.test', $content);
    }

    /**
     * @test
     * @return void
     */
    public function testRemovePreservesManualEntries(): void
    {
        // 'existing.manual.test' was added without the MARKER in setUp
        $this->hosts->add('managed.test');
        $this->hosts->remove('managed.test');

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringContainsString('existing.manual.test', $content);
    }

    /**
     * @test
     * @return void
     */
    public function testRemoveReturnsFalseWhenDomainNotFound(): void
    {
        $result = $this->hosts->remove('ghost.test');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // listManagedDomains()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testListManagedDomainsReturnsOnlyHteCliEntries(): void
    {
        // Add two managed entries; 'existing.manual.test' must NOT appear
        $this->hosts->add('alpha.test');
        $this->hosts->add('beta.test');

        $managed = $this->hosts->listManagedDomains();

        $this->assertContains('alpha.test', $managed);
        $this->assertContains('beta.test', $managed);
        $this->assertNotContains('existing.manual.test', $managed);
    }

    /**
     * @test
     * @return void
     */
    public function testListManagedDomainsReturnsEmptyWhenNone(): void
    {
        // Fresh hosts file with no MARKER lines
        $fs = new InMemoryFilesystem(
            ['/test/hosts' => "127.0.0.1    localhost\n"],
            ['/test']
        );
        $manager = new HostsManager($fs, $this->process, '/test/hosts');

        $this->assertSame([], $manager->listManagedDomains());
    }

    // -------------------------------------------------------------------------
    // sync()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testSyncReplacesAllHteCliEntries(): void
    {
        $this->hosts->add('old.test');

        $result = $this->hosts->sync(['new-alpha.test', 'new-beta.test']);

        $this->assertTrue($result);

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringNotContainsString('old.test', $content);
        $this->assertStringContainsString('new-alpha.test', $content);
        $this->assertStringContainsString('new-beta.test', $content);
    }

    /**
     * @test
     * @return void
     */
    public function testSyncPreservesManualEntries(): void
    {
        $this->hosts->sync(['synced.test']);

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringContainsString('existing.manual.test', $content);
    }

    // -------------------------------------------------------------------------
    // getEntry()
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testGetEntryReturnsCorrectInfoForManagedDomain(): void
    {
        $this->hosts->add('managed.test');

        $entry = $this->hosts->getEntry('managed.test');

        $this->assertIsArray($entry);
        $this->assertArrayHasKey('line', $entry);
        $this->assertArrayHasKey('content', $entry);
        $this->assertArrayHasKey('managed_by_hte_cli', $entry);
        $this->assertTrue($entry['managed_by_hte_cli']);
        $this->assertStringContainsString('managed.test', $entry['content']);
        $this->assertGreaterThan(0, $entry['line']);
    }

    /**
     * @test
     * @return void
     */
    public function testGetEntryReturnsCorrectInfoForManualDomain(): void
    {
        $entry = $this->hosts->getEntry('existing.manual.test');

        $this->assertIsArray($entry);
        $this->assertFalse($entry['managed_by_hte_cli']);
        $this->assertStringContainsString('existing.manual.test', $entry['content']);
    }

    /**
     * @test
     * @return void
     */
    public function testGetEntryReturnsNullForNonExistentDomain(): void
    {
        $entry = $this->hosts->getEntry('nothere.test');

        $this->assertNull($entry);
    }

    // -------------------------------------------------------------------------
    // MARKER constant
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testMarkerConstant(): void
    {
        $this->assertSame('# hte-cli', HostsManagerInterface::MARKER);
    }

    // -------------------------------------------------------------------------
    // Error paths — unreadable / unwritable hosts file
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testExistsReturnsFalseWhenHostsFileUnreadable(): void
    {
        $this->fs->setUnreadable('/test/hosts');

        $this->assertFalse($this->hosts->exists('localhost'));
    }

    /**
     * @test
     * @return void
     */
    public function testAddReturnsFalseWhenHostsFileUnwritable(): void
    {
        $this->fs->setUnwritable('/test/hosts');

        $this->assertFalse($this->hosts->add('new.test'));
    }

    /**
     * @test
     * @return void
     */
    public function testRemoveReturnsFalseWhenHostsFileUnwritable(): void
    {
        $this->fs->setUnwritable('/test/hosts');

        $this->assertFalse($this->hosts->remove('localhost'));
    }

    /**
     * @test
     * @return void
     */
    public function testSyncReturnsFalseWhenHostsFileUnwritable(): void
    {
        $this->fs->setUnwritable('/test/hosts');

        $this->assertFalse($this->hosts->sync(['new.test']));
    }

    /**
     * @test
     * @return void
     */
    public function testGetEntryReturnsNullWhenHostsFileUnreadable(): void
    {
        $this->fs->setUnreadable('/test/hosts');

        $this->assertNull($this->hosts->getEntry('localhost'));
    }

    /**
     * @test
     * @return void
     */
    public function testListManagedDomainsReturnsEmptyWhenHostsFileUnreadable(): void
    {
        $this->fs->setUnreadable('/test/hosts');

        $this->assertSame([], $this->hosts->listManagedDomains());
    }

    // -------------------------------------------------------------------------
    // Word-boundary matching (regression for substring match bug)
    // -------------------------------------------------------------------------

    /**
     * @test
     * @return void
     */
    public function testGetEntryDoesNotMatchSubstringDomains(): void
    {
        // "test" should NOT match "existing.manual.test" (which is in setUp)
        $entry = $this->hosts->getEntry('test');

        // If getEntry correctly uses word-boundary matching,
        // "test" alone should NOT match "existing.manual.test"
        $this->assertNull($entry);
    }

    /**
     * @test
     * @return void
     */
    public function testRemoveDoesNotAffectSubstringDomains(): void
    {
        $this->hosts->add('myapp.test');
        $this->hosts->add('myapp.test.staging');

        // Removing "myapp.test" should NOT remove "myapp.test.staging"
        $this->hosts->remove('myapp.test');

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringContainsString('myapp.test.staging', $content);
    }

    /**
     * @test
     * @return void
     */
    public function testSyncWithEmptyArrayRemovesAllManaged(): void
    {
        $this->hosts->add('one.test');
        $this->hosts->add('two.test');

        $result = $this->hosts->sync([]);

        $this->assertTrue($result);

        $content = $this->fs->getContents('/test/hosts');
        $this->assertStringNotContainsString(HostsManagerInterface::MARKER, $content);
        // Manual entries should survive
        $this->assertStringContainsString('existing.manual.test', $content);
    }
}
