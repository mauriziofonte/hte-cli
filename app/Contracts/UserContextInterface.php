<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for accessing the current user's identity and privilege level.
 */
interface UserContextInterface
{
    /**
     * Get the effective username (resolves SUDO_USER when running under sudo).
     *
     * @return string
     */
    public function getUserName();

    /**
     * Get the user's primary group name.
     *
     * @return string
     */
    public function getUserGroup();

    /**
     * Get the user's home directory.
     *
     * @return string
     */
    public function getUserHome();

    /**
     * Get the user's UID.
     *
     * @return int
     */
    public function getUserUid();

    /**
     * Get the user's GID.
     *
     * @return int
     */
    public function getUserGid();

    /**
     * Check if the process has root-level permissions (running as root or via sudo).
     *
     * @return bool
     */
    public function hasRootPermissions();

    /**
     * Check if the process is running via sudo (as opposed to direct root login).
     *
     * @return bool
     */
    public function isRunningSudo();

    /**
     * Check if the resolved user is the root account itself.
     *
     * @return bool
     */
    public function isRootUser();
}
