<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mfonte\HteCli\Services\UserContext;

/**
 * Tests for UserContext service.
 *
 * Uses fake $_SERVER arrays to simulate different user environments
 * without requiring actual POSIX user lookups for privilege detection.
 */
class UserContextTest extends TestCase
{
    public function testDetectsRegularUser(): void
    {
        // Use the actual running user for this test
        $currentUser = posix_getpwuid(posix_getuid());
        if ($currentUser === false) {
            $this->markTestSkipped('Cannot determine current user via POSIX.');
        }

        $ctx = new UserContext(['USER' => $currentUser['name']]);

        $this->assertEquals($currentUser['name'], $ctx->getUserName());
        $this->assertFalse($ctx->hasRootPermissions());
        $this->assertFalse($ctx->isRunningSudo());
        $this->assertFalse($ctx->isRootUser());
    }

    public function testResolvesUserGroup(): void
    {
        $currentUser = posix_getpwuid(posix_getuid());
        if ($currentUser === false) {
            $this->markTestSkipped('Cannot determine current user via POSIX.');
        }

        $ctx = new UserContext(['USER' => $currentUser['name']]);
        $expectedGroup = posix_getgrgid($currentUser['gid']);

        $this->assertEquals($expectedGroup['name'], $ctx->getUserGroup());
    }

    public function testResolvesUserHome(): void
    {
        $currentUser = posix_getpwuid(posix_getuid());
        if ($currentUser === false) {
            $this->markTestSkipped('Cannot determine current user via POSIX.');
        }

        $ctx = new UserContext(['USER' => $currentUser['name']]);

        $this->assertEquals($currentUser['dir'], $ctx->getUserHome());
    }

    public function testResolvesUserUidAndGid(): void
    {
        $currentUser = posix_getpwuid(posix_getuid());
        if ($currentUser === false) {
            $this->markTestSkipped('Cannot determine current user via POSIX.');
        }

        $ctx = new UserContext(['USER' => $currentUser['name']]);

        $this->assertEquals($currentUser['uid'], $ctx->getUserUid());
        $this->assertEquals($currentUser['gid'], $ctx->getUserGid());
    }

    public function testThrowsExceptionForEmptyUser(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get the current user name');

        $ctx = new UserContext(['USER' => '']);
        $ctx->getUserName();
    }

    public function testThrowsExceptionForMissingUser(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get the current user name');

        $ctx = new UserContext([]);
        $ctx->getUserName();
    }

    public function testThrowsExceptionForNonExistentUser(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $ctx = new UserContext(['USER' => 'nonexistent_user_xyz_12345']);
        $ctx->getUserName();
    }

    public function testLazyResolution(): void
    {
        // Constructor should not throw, even with invalid data.
        // Resolution happens on first getter call.
        $ctx = new UserContext(['USER' => '']);
        $this->assertInstanceOf(UserContext::class, $ctx);
    }

    // -------------------------------------------------------------------------
    // Sudo / root detection
    // -------------------------------------------------------------------------

    public function testDetectsSudoExecution(): void
    {
        $currentUser = posix_getpwuid(posix_getuid());
        if ($currentUser === false) {
            $this->markTestSkipped('Cannot determine current user via POSIX.');
        }

        // Simulate: USER=root, SUDO_USER=<real user> (this is what sudo does)
        $ctx = new UserContext([
            'USER' => 'root',
            'SUDO_USER' => $currentUser['name'],
        ]);

        // Should resolve to the original (non-root) user
        $this->assertEquals($currentUser['name'], $ctx->getUserName());
        $this->assertTrue($ctx->hasRootPermissions());
        $this->assertTrue($ctx->isRunningSudo());
        $this->assertFalse($ctx->isRootUser());
    }

    public function testDetectsDirectRootLogin(): void
    {
        // Direct root login: USER=root, no SUDO_USER
        $rootUser = posix_getpwnam('root');
        if ($rootUser === false) {
            $this->markTestSkipped('Root user not available via POSIX.');
        }

        $ctx = new UserContext(['USER' => 'root']);

        $this->assertEquals('root', $ctx->getUserName());
        $this->assertTrue($ctx->hasRootPermissions());
        $this->assertFalse($ctx->isRunningSudo());
        $this->assertTrue($ctx->isRootUser());
    }

    public function testSudoUserResolvesOriginalUserUidGid(): void
    {
        $currentUser = posix_getpwuid(posix_getuid());
        if ($currentUser === false) {
            $this->markTestSkipped('Cannot determine current user via POSIX.');
        }

        $ctx = new UserContext([
            'USER' => 'root',
            'SUDO_USER' => $currentUser['name'],
        ]);

        // UID/GID should be of the original user, not root
        $this->assertEquals($currentUser['uid'], $ctx->getUserUid());
        $this->assertEquals($currentUser['gid'], $ctx->getUserGid());
        $this->assertEquals($currentUser['dir'], $ctx->getUserHome());
    }

    public function testSudoWithNonExistentOriginalUserThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $ctx = new UserContext([
            'USER' => 'root',
            'SUDO_USER' => 'nonexistent_user_xyz_99999',
        ]);
        $ctx->getUserName();
    }
}
