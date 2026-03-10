<?php

namespace Mfonte\HteCli\Contracts;

/**
 * Contract for checking the runtime environment requirements.
 */
interface EnvironmentCheckerInterface
{
    /**
     * Check if the current OS is supported (non-Windows).
     *
     * @return bool
     */
    public function isSupportedOs();

    /**
     * Check if the process is running from a terminal shell.
     *
     * @return bool
     */
    public function hasShell();

    /**
     * Check if a system binary is available.
     *
     * @param string $binary Binary name (e.g., 'apache2', 'php').
     * @return bool
     */
    public function hasBinary(string $binary);

    /**
     * Check if a PHP function exists and is not disabled.
     *
     * @param string $function
     * @return bool
     */
    public function hasFunction(string $function);

    /**
     * Get a list of required PHP functions for HTE-CLI.
     *
     * @return array
     */
    public function getRequiredFunctions();

    /**
     * Get a list of required system binaries for HTE-CLI.
     *
     * @return array
     */
    public function getRequiredBinaries();
}
