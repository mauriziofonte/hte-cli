<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Doubles\InMemoryFilesystem;

/**
 * Self-tests for the InMemoryFilesystem test double.
 *
 * Ensures the in-memory implementation behaves consistently with
 * real filesystem semantics so it can be trusted in other tests.
 */
class InMemoryFilesystemTest extends TestCase
{
    /** @var InMemoryFilesystem */
    private $fs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fs = new InMemoryFilesystem();
    }

    public function testFileExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->fs->fileExists('/nonexistent'));
    }

    public function testPutContentsCreatesFile(): void
    {
        $this->fs->putContents('/tmp/test.txt', 'hello');

        $this->assertTrue($this->fs->fileExists('/tmp/test.txt'));
        $this->assertEquals('hello', $this->fs->getContents('/tmp/test.txt'));
    }

    public function testPutContentsWithAppendFlag(): void
    {
        $this->fs->putContents('/tmp/append.txt', 'first');
        $this->fs->putContents('/tmp/append.txt', ' second', FILE_APPEND);

        $this->assertEquals('first second', $this->fs->getContents('/tmp/append.txt'));
    }

    public function testGetContentsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->fs->getContents('/missing'));
    }

    public function testDeleteRemovesFile(): void
    {
        $this->fs->putContents('/tmp/delete.txt', 'data');
        $this->assertTrue($this->fs->delete('/tmp/delete.txt'));
        $this->assertFalse($this->fs->fileExists('/tmp/delete.txt'));
    }

    public function testDeleteReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->fs->delete('/nonexistent'));
    }

    public function testMakeDirectoryCreatesDir(): void
    {
        $this->fs->makeDirectory('/tmp/dir', 0755, false);

        $this->assertTrue($this->fs->isDir('/tmp/dir'));
    }

    public function testMakeDirectoryRecursive(): void
    {
        $this->fs->makeDirectory('/a/b/c/d', 0755, true);

        $this->assertTrue($this->fs->isDir('/a'));
        $this->assertTrue($this->fs->isDir('/a/b'));
        $this->assertTrue($this->fs->isDir('/a/b/c'));
        $this->assertTrue($this->fs->isDir('/a/b/c/d'));
    }

    public function testIsDirReturnsFalseForFiles(): void
    {
        $this->fs->putContents('/tmp/file.txt', 'data');

        $this->assertFalse($this->fs->isDir('/tmp/file.txt'));
    }

    public function testIsWritableForExistingFile(): void
    {
        $this->fs->putContents('/tmp/writable.txt', 'data');

        $this->assertTrue($this->fs->isWritable('/tmp/writable.txt'));
    }

    public function testIsReadableForExistingFile(): void
    {
        $this->fs->putContents('/tmp/readable.txt', 'data');

        $this->assertTrue($this->fs->isReadable('/tmp/readable.txt'));
    }

    public function testSymlinkCreatesLink(): void
    {
        $this->fs->symlink('/target/file', '/link/file');

        $this->assertTrue($this->fs->isLink('/link/file'));
        $this->assertTrue($this->fs->fileExists('/link/file'));
    }

    public function testDeleteRemovesSymlink(): void
    {
        $this->fs->symlink('/target', '/link');
        $this->assertTrue($this->fs->delete('/link'));
        $this->assertFalse($this->fs->isLink('/link'));
    }

    public function testRenameMovesFile(): void
    {
        $this->fs->putContents('/old.txt', 'content');
        $this->assertTrue($this->fs->rename('/old.txt', '/new.txt'));

        $this->assertFalse($this->fs->fileExists('/old.txt'));
        $this->assertTrue($this->fs->fileExists('/new.txt'));
        $this->assertEquals('content', $this->fs->getContents('/new.txt'));
    }

    public function testRenameMovesDirectoryAndContents(): void
    {
        $this->fs->makeDirectory('/old-dir', 0755, false);
        $this->fs->putContents('/old-dir/file.txt', 'data');

        $this->assertTrue($this->fs->rename('/old-dir', '/new-dir'));

        $this->assertFalse($this->fs->isDir('/old-dir'));
        $this->assertTrue($this->fs->isDir('/new-dir'));
        $this->assertEquals('data', $this->fs->getContents('/new-dir/file.txt'));
    }

    public function testRenameReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->fs->rename('/nonexistent', '/other'));
    }

    public function testTempFileCreatesUniqueFiles(): void
    {
        $path1 = $this->fs->tempFile('/tmp', 'prefix');
        $path2 = $this->fs->tempFile('/tmp', 'prefix');

        $this->assertNotEquals($path1, $path2);
        $this->assertTrue($this->fs->fileExists($path1));
        $this->assertTrue($this->fs->fileExists($path2));
    }

    public function testRemoveDirectoryDeletesAll(): void
    {
        $this->fs->makeDirectory('/dir/sub', 0755, true);
        $this->fs->putContents('/dir/file.txt', 'data');
        $this->fs->putContents('/dir/sub/nested.txt', 'nested');

        $this->assertTrue($this->fs->removeDirectory('/dir'));

        $this->assertFalse($this->fs->isDir('/dir'));
        $this->assertFalse($this->fs->isDir('/dir/sub'));
        $this->assertFalse($this->fs->fileExists('/dir/file.txt'));
        $this->assertFalse($this->fs->fileExists('/dir/sub/nested.txt'));
    }

    public function testFindByExtension(): void
    {
        $this->fs->putContents('/search/a.conf', 'a');
        $this->fs->putContents('/search/b.conf', 'b');
        $this->fs->putContents('/search/c.txt', 'c');
        $this->fs->putContents('/search/sub/d.conf', 'd');

        $result = $this->fs->findByExtension('/search', 'conf');

        $this->assertCount(3, $result);
    }

    public function testFindByExtensionNonRecursive(): void
    {
        $this->fs->putContents('/search/a.conf', 'a');
        $this->fs->putContents('/search/sub/b.conf', 'b');

        $result = $this->fs->findByExtension('/search', 'conf', false);

        $this->assertCount(1, $result);
        $this->assertEquals('/search/a.conf', $result[0]);
    }

    public function testFindDirectories(): void
    {
        $this->fs->makeDirectory('/php/7.4', 0755, true);
        $this->fs->makeDirectory('/php/8.0', 0755, true);
        $this->fs->makeDirectory('/php/8.4', 0755, true);
        $this->fs->makeDirectory('/php/other', 0755, true);

        $result = $this->fs->findDirectories('/php', ['7.4', '8.0', '8.4']);

        $this->assertCount(3, $result);
        $this->assertContains('/php/7.4', $result);
        $this->assertContains('/php/8.0', $result);
        $this->assertContains('/php/8.4', $result);
    }

    public function testFindDirectoriesWithFnmatchPattern(): void
    {
        $this->fs->makeDirectory('/php/7.4', 0755, true);
        $this->fs->makeDirectory('/php/8.0', 0755, true);

        $result = $this->fs->findDirectories('/php', ['8.*'], false, false);

        $this->assertCount(1, $result);
        $this->assertContains('/php/8.0', $result);
    }

    public function testReadLinesWithIgnoreNewLines(): void
    {
        $this->fs->putContents('/tmp/lines.txt', "line1\nline2\nline3");

        $lines = $this->fs->readLines('/tmp/lines.txt', FILE_IGNORE_NEW_LINES);

        $this->assertCount(3, $lines);
        $this->assertEquals('line1', $lines[0]);
        $this->assertEquals('line2', $lines[1]);
        $this->assertEquals('line3', $lines[2]);
    }

    public function testReadLinesReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->fs->readLines('/nonexistent'));
    }

    public function testConstructorPrePopulates(): void
    {
        $fs = new InMemoryFilesystem(
            ['/file.txt' => 'content'],
            ['/dir/sub']
        );

        $this->assertTrue($fs->fileExists('/file.txt'));
        $this->assertEquals('content', $fs->getContents('/file.txt'));
        $this->assertTrue($fs->isDir('/dir'));
        $this->assertTrue($fs->isDir('/dir/sub'));
    }

    public function testChmodStoresPermission(): void
    {
        $this->fs->putContents('/tmp/perm.txt', 'data');
        $this->assertTrue($this->fs->chmod('/tmp/perm.txt', 0755));
    }

    public function testPutContentsCreatesParentDirectories(): void
    {
        $this->fs->putContents('/deep/nested/path/file.txt', 'data');

        $this->assertTrue($this->fs->isDir('/deep'));
        $this->assertTrue($this->fs->isDir('/deep/nested'));
        $this->assertTrue($this->fs->isDir('/deep/nested/path'));
        $this->assertTrue($this->fs->fileExists('/deep/nested/path/file.txt'));
    }
}
