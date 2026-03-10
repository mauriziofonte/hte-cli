<?php

namespace Mfonte\HteCli\Services;

use Mfonte\HteCli\Contracts\UserContextInterface;

/**
 * Resolves the current user's identity from $_SERVER and POSIX functions.
 *
 * Handles sudo detection: when running under sudo, resolves the original
 * (non-root) user via SUDO_USER.
 */
class UserContext implements UserContextInterface
{
    /** @var string */
    private $userName;

    /** @var string */
    private $userGroup;

    /** @var string */
    private $userHome;

    /** @var int */
    private $userUid;

    /** @var int */
    private $userGid;

    /** @var bool */
    private $hasRoot;

    /** @var bool */
    private $isSudo;

    /** @var bool */
    private $resolved = false;

    /** @var array|null Override for $_SERVER in tests. */
    private $serverVars;

    /**
     * @param array|null $serverVars Optional override for $_SERVER (used in tests).
     */
    public function __construct($serverVars = null)
    {
        $this->serverVars = $serverVars;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserName()
    {
        $this->resolve();
        return $this->userName;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserGroup()
    {
        $this->resolve();
        return $this->userGroup;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserHome()
    {
        $this->resolve();
        return $this->userHome;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserUid()
    {
        $this->resolve();
        return $this->userUid;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserGid()
    {
        $this->resolve();
        return $this->userGid;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootPermissions()
    {
        $this->resolve();
        return $this->hasRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunningSudo()
    {
        $this->resolve();
        return $this->isSudo;
    }

    /**
     * {@inheritdoc}
     */
    public function isRootUser()
    {
        $this->resolve();
        return $this->userName === 'root';
    }

    /**
     * Lazily resolve user identity from the environment.
     *
     * @throws \RuntimeException If user cannot be resolved.
     */
    private function resolve()
    {
        if ($this->resolved) {
            return;
        }

        $server = $this->serverVars !== null ? $this->serverVars : $_SERVER;
        $uname = isset($server['USER']) ? $server['USER'] : '';

        if (empty($uname)) {
            throw new \RuntimeException('Failed to get the current user name. Make sure to run from a terminal.');
        }

        $this->hasRoot = false;
        $this->isSudo = false;

        if ($uname === 'root' && !array_key_exists('SUDO_USER', $server)) {
            // Direct root login
            $this->hasRoot = true;
        } elseif ($uname === 'root' && array_key_exists('SUDO_USER', $server)) {
            // Running via sudo
            $this->hasRoot = true;
            $this->isSudo = true;
            $uname = $server['SUDO_USER'];
        }

        $userInfo = posix_getpwnam($uname);
        if (empty($userInfo)) {
            throw new \RuntimeException("User {$uname} does not exist.");
        }

        $this->userName = $userInfo['name'];
        $groupInfo = posix_getgrgid($userInfo['gid']);
        $this->userGroup = $groupInfo ? $groupInfo['name'] : (string) $userInfo['gid'];
        $this->userHome = $userInfo['dir'];
        $this->userUid = $userInfo['uid'];
        $this->userGid = $userInfo['gid'];
        $this->resolved = true;
    }
}
