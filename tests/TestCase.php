<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for all HTE-CLI tests.
 *
 * Provides helper methods for creating temporary filesystem structures
 * used by integration-style tests. Unit tests should prefer
 * InMemoryFilesystem from tests/Doubles instead.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Root directory for test filesystem operations.
     *
     * @var string|null
     */
    protected $testRoot;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->testRoot !== null && is_dir($this->testRoot)) {
            $this->recursiveRemoveDirectory($this->testRoot);
        }

        parent::tearDown();
    }

    /**
     * Initialize a temporary directory tree for tests that need real filesystem.
     *
     * Call this in setUp() of tests that require a real temp directory.
     * The directory is automatically cleaned up in tearDown().
     *
     * @return string The test root path.
     */
    protected function initTestRoot()
    {
        $this->testRoot = sys_get_temp_dir() . '/hte-cli-test-' . uniqid();
        mkdir($this->testRoot, 0755, true);
        return $this->testRoot;
    }

    /**
     * Recursively remove a directory and its contents.
     *
     * @param string $dir
     * @return void
     */
    protected function recursiveRemoveDirectory(string $dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Create a test file with the given content under testRoot.
     *
     * @param string $relativePath Path relative to testRoot.
     * @param string $content File content.
     * @return string Full path to the created file.
     */
    protected function createTestFile(string $relativePath, string $content)
    {
        if ($this->testRoot === null) {
            $this->initTestRoot();
        }

        $fullPath = $this->testRoot . '/' . ltrim($relativePath, '/');
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    /**
     * Assert that a file contains a specific string.
     *
     * @param string $needle
     * @param string $filePath
     * @param string $message
     * @return void
     */
    protected function assertFileContainsString(string $needle, string $filePath, string $message = '')
    {
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertStringContainsString($needle, $content, $message);
    }

    /**
     * Assert that a file does not contain a specific string.
     *
     * @param string $needle
     * @param string $filePath
     * @param string $message
     * @return void
     */
    protected function assertFileNotContainsString(string $needle, string $filePath, string $message = '')
    {
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertStringNotContainsString($needle, $content, $message);
    }
}
