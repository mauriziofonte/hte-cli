<?php

namespace Tests\Doubles;

use Mfonte\HteCli\Contracts\ProcessExecutorInterface;

/**
 * Fake process executor for testing.
 *
 * Returns pre-configured responses based on command patterns.
 * Records all executed commands for assertion.
 */
class FakeProcessExecutor implements ProcessExecutorInterface
{
    /** @var array Pre-configured responses: [pattern => [exitCode, stdout, stderr]]. */
    private $responses = [];

    /** @var array Default response when no pattern matches. */
    private $defaultResponse = [0, '', ''];

    /** @var array List of all executed commands. */
    private $executedCommands = [];

    /**
     * Register a response for a command pattern.
     *
     * @param string $commandPattern Substring to match in the command.
     * @param int $exitCode
     * @param string $stdout
     * @param string $stderr
     * @return self
     */
    public function addResponse(string $commandPattern, int $exitCode, string $stdout = '', string $stderr = '')
    {
        $this->responses[] = [
            'pattern' => $commandPattern,
            'response' => [$exitCode, $stdout, $stderr],
        ];
        return $this;
    }

    /**
     * Set the default response for unmatched commands.
     *
     * @param int $exitCode
     * @param string $stdout
     * @param string $stderr
     * @return self
     */
    public function setDefaultResponse(int $exitCode, string $stdout = '', string $stderr = '')
    {
        $this->defaultResponse = [$exitCode, $stdout, $stderr];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $command)
    {
        $this->executedCommands[] = $command;

        // Find the first matching response
        foreach ($this->responses as $entry) {
            if (strpos($command, $entry['pattern']) !== false) {
                return $entry['response'];
            }
        }

        return $this->defaultResponse;
    }

    /**
     * Get all executed commands.
     *
     * @return array
     */
    public function getExecutedCommands()
    {
        return $this->executedCommands;
    }

    /**
     * Check if a command matching the pattern was executed.
     *
     * @param string $pattern Substring to search in executed commands.
     * @return bool
     */
    public function wasExecuted(string $pattern)
    {
        foreach ($this->executedCommands as $cmd) {
            if (strpos($cmd, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the number of times a command matching the pattern was executed.
     *
     * @param string $pattern
     * @return int
     */
    public function executionCount(string $pattern)
    {
        $count = 0;
        foreach ($this->executedCommands as $cmd) {
            if (strpos($cmd, $pattern) !== false) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Reset recorded commands.
     *
     * @return self
     */
    public function reset()
    {
        $this->executedCommands = [];
        return $this;
    }
}
