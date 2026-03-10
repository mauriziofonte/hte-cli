<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mfonte\HteCli\Services\SystemFilesystem;

/**
 * Integration tests for SystemFilesystem.
 *
 * These tests exercise the real filesystem implementation using
 * temporary directories that are cleaned up after each test.
 */
class SystemFilesystemTest extends TestCase
{
    /** @var SystemFilesystem */
    private $fs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fs = new SystemFilesystem();
        $this->initTestRoot();
    }

    // ── Basic file operations ────────────────────────────────────────

    public function testFileExistsReturnsTrueForFile(): void
    {
        file_put_contents($this->testRoot . '/test.txt', 'data');

        $this->assertTrue($this->fs->fileExists($this->testRoot . '/test.txt'));
    }

    public function testFileExistsReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->fs->fileExists($this->testRoot . '/nope.txt'));
    }

    public function testFileExistsReturnsFalseForDirectory(): void
    {
        mkdir($this->testRoot . '/subdir');

        $this->assertFalse($this->fs->fileExists($this->testRoot . '/subdir'));
    }

    public function testGetContentsReadsFile(): void
    {
        file_put_contents($this->testRoot . '/read.txt', 'hello world');

        $this->assertEquals('hello world', $this->fs->getContents($this->testRoot . '/read.txt'));
    }

    public function testPutContentsCreatesFile(): void
    {
        $this->fs->putContents($this->testRoot . '/new.txt', 'content');

        $this->assertFileExists($this->testRoot . '/new.txt');
        $this->assertEquals('content', file_get_contents($this->testRoot . '/new.txt'));
    }

    public function testPutContentsWithAppendFlag(): void
    {
        $this->fs->putContents($this->testRoot . '/append.txt', 'first');
        $this->fs->putContents($this->testRoot . '/append.txt', ' second', FILE_APPEND);

        $this->assertEquals('first second', file_get_contents($this->testRoot . '/append.txt'));
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function testDeleteRemovesFile(): void
    {
        file_put_contents($this->testRoot . '/del.txt', 'data');

        $this->assertTrue($this->fs->delete($this->testRoot . '/del.txt'));
        $this->assertFileDoesNotExist($this->testRoot . '/del.txt');
    }

    public function testDeleteReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->fs->delete($this->testRoot . '/nonexistent'));
    }

    public function testDeleteReturnsFalseForDirectory(): void
    {
        mkdir($this->testRoot . '/dir');

        $this->assertFalse($this->fs->delete($this->testRoot . '/dir'));
    }

    // ── Directory operations ─────────────────────────────────────────

    public function testIsDirReturnsTrueForDirectory(): void
    {
        mkdir($this->testRoot . '/mydir');

        $this->assertTrue($this->fs->isDir($this->testRoot . '/mydir'));
    }

    public function testIsDirReturnsFalseForFile(): void
    {
        file_put_contents($this->testRoot . '/file.txt', 'data');

        $this->assertFalse($this->fs->isDir($this->testRoot . '/file.txt'));
    }

    public function testMakeDirectoryCreatesDir(): void
    {
        $this->fs->makeDirectory($this->testRoot . '/newdir');

        $this->assertTrue(is_dir($this->testRoot . '/newdir'));
    }

    public function testMakeDirectoryRecursive(): void
    {
        $this->fs->makeDirectory($this->testRoot . '/a/b/c', 0755, true);

        $this->assertTrue(is_dir($this->testRoot . '/a/b/c'));
    }

    public function testMakeDirectoryReturnsTrueIfAlreadyExists(): void
    {
        mkdir($this->testRoot . '/existing');

        $this->assertTrue($this->fs->makeDirectory($this->testRoot . '/existing'));
    }

    // ── removeDirectory ──────────────────────────────────────────────

    public function testRemoveDirectoryDeletesRecursively(): void
    {
        mkdir($this->testRoot . '/rmdir/sub', 0755, true);
        file_put_contents($this->testRoot . '/rmdir/file.txt', 'data');
        file_put_contents($this->testRoot . '/rmdir/sub/nested.txt', 'nested');

        $this->assertTrue($this->fs->removeDirectory($this->testRoot . '/rmdir'));
        $this->assertFalse(is_dir($this->testRoot . '/rmdir'));
    }

    public function testRemoveDirectoryReturnsFalseForNonexistent(): void
    {
        $this->assertFalse($this->fs->removeDirectory($this->testRoot . '/nope'));
    }

    public function testRemoveDirectoryDeletesSingleFile(): void
    {
        file_put_contents($this->testRoot . '/single.txt', 'data');

        $this->assertTrue($this->fs->removeDirectory($this->testRoot . '/single.txt'));
        $this->assertFileDoesNotExist($this->testRoot . '/single.txt');
    }

    // ── Rename ───────────────────────────────────────────────────────

    public function testRenameMovesFile(): void
    {
        file_put_contents($this->testRoot . '/old.txt', 'content');

        $this->assertTrue($this->fs->rename($this->testRoot . '/old.txt', $this->testRoot . '/new.txt'));
        $this->assertFileDoesNotExist($this->testRoot . '/old.txt');
        $this->assertEquals('content', file_get_contents($this->testRoot . '/new.txt'));
    }

    // ── Permissions ──────────────────────────────────────────────────

    public function testIsWritableForCreatedFile(): void
    {
        file_put_contents($this->testRoot . '/w.txt', 'data');

        $this->assertTrue($this->fs->isWritable($this->testRoot . '/w.txt'));
    }

    public function testIsReadableForCreatedFile(): void
    {
        file_put_contents($this->testRoot . '/r.txt', 'data');

        $this->assertTrue($this->fs->isReadable($this->testRoot . '/r.txt'));
    }

    public function testChmodChangesPermissions(): void
    {
        file_put_contents($this->testRoot . '/perm.txt', 'data');

        $this->assertTrue($this->fs->chmod($this->testRoot . '/perm.txt', 0644));
    }

    // ── Symlink ──────────────────────────────────────────────────────

    public function testSymlinkCreatesLink(): void
    {
        file_put_contents($this->testRoot . '/target.txt', 'data');
        $this->fs->symlink($this->testRoot . '/target.txt', $this->testRoot . '/link.txt');

        $this->assertTrue($this->fs->isLink($this->testRoot . '/link.txt'));
    }

    public function testDeleteRemovesSymlink(): void
    {
        file_put_contents($this->testRoot . '/target2.txt', 'data');
        symlink($this->testRoot . '/target2.txt', $this->testRoot . '/link2.txt');

        $this->assertTrue($this->fs->delete($this->testRoot . '/link2.txt'));
        $this->assertFalse(is_link($this->testRoot . '/link2.txt'));
    }

    // ── tempFile ─────────────────────────────────────────────────────

    public function testTempFileCreatesUniqueFiles(): void
    {
        $path1 = $this->fs->tempFile($this->testRoot, 'pfx');
        $path2 = $this->fs->tempFile($this->testRoot, 'pfx');

        $this->assertNotEquals($path1, $path2);
        $this->assertFileExists($path1);
        $this->assertFileExists($path2);
    }

    // ── readLines ────────────────────────────────────────────────────

    public function testReadLinesReturnsArray(): void
    {
        file_put_contents($this->testRoot . '/lines.txt', "line1\nline2\nline3");

        $lines = $this->fs->readLines($this->testRoot . '/lines.txt', FILE_IGNORE_NEW_LINES);

        $this->assertCount(3, $lines);
        $this->assertEquals('line1', $lines[0]);
        $this->assertEquals('line3', $lines[2]);
    }

    // ── findByExtension ──────────────────────────────────────────────

    public function testFindByExtensionFindsFilesRecursively(): void
    {
        mkdir($this->testRoot . '/search/sub', 0755, true);
        file_put_contents($this->testRoot . '/search/a.conf', 'a');
        file_put_contents($this->testRoot . '/search/b.conf', 'b');
        file_put_contents($this->testRoot . '/search/c.txt', 'c');
        file_put_contents($this->testRoot . '/search/sub/d.conf', 'd');

        $result = $this->fs->findByExtension($this->testRoot . '/search', 'conf');

        $this->assertCount(3, $result);
    }

    public function testFindByExtensionNonRecursive(): void
    {
        mkdir($this->testRoot . '/search2/sub', 0755, true);
        file_put_contents($this->testRoot . '/search2/a.conf', 'a');
        file_put_contents($this->testRoot . '/search2/sub/b.conf', 'b');

        $result = $this->fs->findByExtension($this->testRoot . '/search2', 'conf', false);

        $this->assertCount(1, $result);
    }

    public function testFindByExtensionReturnsEmptyForMissingDir(): void
    {
        $result = $this->fs->findByExtension($this->testRoot . '/nonexistent', 'conf');

        $this->assertEmpty($result);
    }

    // ── findDirectories ──────────────────────────────────────────────

    public function testFindDirectoriesStrictMatch(): void
    {
        mkdir($this->testRoot . '/php/7.4', 0755, true);
        mkdir($this->testRoot . '/php/8.0', 0755, true);
        mkdir($this->testRoot . '/php/8.4', 0755, true);
        mkdir($this->testRoot . '/php/other', 0755, true);

        $result = $this->fs->findDirectories($this->testRoot . '/php', ['7.4', '8.0', '8.4']);

        $this->assertCount(3, $result);
    }

    public function testFindDirectoriesFnmatchPattern(): void
    {
        mkdir($this->testRoot . '/php2/7.4', 0755, true);
        mkdir($this->testRoot . '/php2/8.0', 0755, true);
        mkdir($this->testRoot . '/php2/8.4', 0755, true);

        $result = $this->fs->findDirectories($this->testRoot . '/php2', ['8.*'], false, false);

        $this->assertCount(2, $result);
    }

    public function testFindDirectoriesReturnsEmptyForMissingDir(): void
    {
        $result = $this->fs->findDirectories($this->testRoot . '/nonexistent', ['foo']);

        $this->assertEmpty($result);
    }
}
