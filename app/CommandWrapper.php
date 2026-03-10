<?php

namespace Mfonte\HteCli;

use LaravelZero\Framework\Commands\Command;
use Mfonte\HteCli\Contracts\EnvironmentCheckerInterface;
use Mfonte\HteCli\Contracts\UserContextInterface;
use Mfonte\HteCli\Contracts\ProcessExecutorInterface;
use Mfonte\HteCli\Exceptions\CriticalErrorException;

/**
 * Base command for all HTE-CLI commands.
 *
 * Handles pre-flight checks (OS, binaries, PHP functions, user identity),
 * banner display, TTY detection, and error rendering. Subclasses call
 * preRun() at the top of handle() to perform these checks.
 *
 * CriticalErrorException is thrown instead of exit(1) so that error
 * flow is testable. The execute() override catches it, renders an
 * error box, and returns exit code 1.
 */
class CommandWrapper extends Command
{
    /** @var string Current working directory of the CLI invocation. */
    protected $cwd;

    /** @var string Base name of the executable (e.g., 'hte-cli'). */
    protected $commandName;

    /** @var string Full path to the running PHP script. */
    protected $fullExecutablePath;

    /** @var int Terminal height in lines. */
    private $ttyLines = 30;

    /** @var int Terminal width in columns. */
    private $ttyCols = 120;

    /** @var EnvironmentCheckerInterface|null */
    protected $envChecker;

    /** @var UserContextInterface|null */
    protected $userContext;

    /** @var ProcessExecutorInterface|null */
    protected $process;

    /**
     * {@inheritdoc}
     *
     * Resolves injected services from the container if not already set.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Wrap command execution to catch CriticalErrorException.
     *
     * When a subclass (or preRun) throws CriticalErrorException, the
     * exception is caught here, an error box is rendered, and exit code 1
     * is returned instead of terminating the process with exit().
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        try {
            return (int) parent::execute($input, $output);
        } catch (CriticalErrorException $e) {
            $this->renderErrorBox($e->getMessage());
            return 1;
        }
    }

    /**
     * Pre-flight checks that must run before ANY command.
     *
     * Displays the banner, verifies the OS, required binaries and PHP
     * functions, detects TTY dimensions, and resolves the running user.
     *
     * @throws CriticalErrorException If any pre-flight check fails.
     * @return void
     */
    protected function preRun()
    {
        // Resolve services from the container (lazy, so tests can override)
        $this->resolveServices();

        // Banner
        $this->showBanner();

        // Environment checks
        if (!$this->envChecker->isSupportedOs()) {
            $this->criticalError('HTE-Cli does not support Windows. Please run it on a Linux or MacOS machine.');
        }

        if (!$this->envChecker->hasShell()) {
            $this->criticalError('HTE-Cli must be run from a terminal.');
        }

        foreach ($this->envChecker->getRequiredBinaries() as $binary) {
            if (!$this->envChecker->hasBinary($binary)) {
                $this->criticalError(
                    "{$binary} is not installed on this system. " .
                    'HTE-Cli requires it to be installed. More info at https://github.com/mauriziofonte/hte-cli'
                );
            }
        }

        foreach ($this->envChecker->getRequiredFunctions() as $function) {
            if (!$this->envChecker->hasFunction($function)) {
                $this->criticalError(
                    "Your PHP installation does not support {$function}() function. " .
                    'Check that it is enabled in your php.ini config.'
                );
            }
        }

        // TTY dimensions
        $this->detectTtySize();
    }

    /**
     * Check if the current user can run privileged commands (root or sudo).
     *
     * @return bool
     */
    protected function canRunPrivilegedCommands()
    {
        $this->resolveServices();
        return $this->userContext->hasRootPermissions();
    }

    /**
     * Throw CriticalErrorException to abort command execution.
     *
     * @param string $message Human-readable error description.
     * @throws CriticalErrorException Always.
     * @return void
     */
    protected function criticalError(string $message)
    {
        throw new CriticalErrorException($message);
    }

    /**
     * Ask a question repeatedly until the validator returns a truthy value.
     *
     * If the validator returns a non-empty string, that string is used as the
     * "transformed" answer (e.g., trimmed, lowercased). Otherwise the raw
     * user input is returned.
     *
     * @param string $question Prompt text.
     * @param string $default Default answer.
     * @param callable $validate Validator: receives the answer, returns truthy on success.
     * @param string $invalidAnswerMsg Error message shown on invalid input.
     * @return string
     */
    protected function keepAsking(string $question, string $default, callable $validate, string $invalidAnswerMsg = 'Invalid input. Please try again.')
    {
        while (true) {
            $answer = $this->ask($question, $default);
            $valid = $validate($answer);

            if ($valid) {
                // Validator returned a transformed string
                if (is_string($valid)) {
                    return $valid;
                }

                return $answer;
            }

            $this->error($invalidAnswerMsg);
        }
    }

    /**
     * Convenience: require root/sudo privileges or abort.
     *
     * @throws CriticalErrorException If the user lacks privileges.
     * @return void
     */
    protected function requirePrivileges()
    {
        if (!$this->canRunPrivilegedCommands()) {
            $this->criticalError('You need to run this command as root or with sudo privileges.');
        }
    }

    /**
     * Convenience: require a non-root effective user or abort.
     *
     * Prevents commands from running as the actual root account (as opposed
     * to a regular user elevated via sudo).
     *
     * @throws CriticalErrorException If the effective user is root.
     * @return void
     */
    protected function requireNonRootUser()
    {
        $this->resolveServices();

        if ($this->userContext->isRootUser()) {
            $this->criticalError('You need to run this command as a regular user, or as a sudoer.');
        }
    }

    /**
     * Display the HTE-CLI ASCII banner and version info.
     *
     * @return void
     */
    protected function showBanner()
    {
        $this->line("\e[1;97m   __ __ ______ ____      _____ __ _  \e[0m");
        $this->line("\e[1;97m  / // //_  __// __/____ / ___// /(_) \e[0m");
        $this->line("\e[1;97m / _  /  / /  / _/ /___// /__ / // /  \e[0m");
        $this->line("\e[1;97m/_//_/  /_/  /___/      \\___//_//_/   \e[0m");
        $this->line("\e[1;97m                                      \e[0m");
        $this->output->writeln(
            "<info>[H]andle [T]est [E]nvironment Cli Tool</info> version <comment>{$this->getApplication()->getVersion()}</comment> by <fg=cyan>Maurizio Fonte</>"
        );
        $this->output->writeln(
            '<bg=red;fg=white;options=bold>WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS.</> Use it only on local/firewalled networks.'
        );
        $this->output->writeln('');
    }

    /**
     * Resolve injected services from the Laravel container.
     *
     * Called lazily so that test setups can bind test doubles before
     * the first access.
     *
     * @return void
     */
    protected function resolveServices()
    {
        if ($this->envChecker === null) {
            $this->envChecker = app(EnvironmentCheckerInterface::class);
        }
        if ($this->userContext === null) {
            $this->userContext = app(UserContextInterface::class);
        }
        if ($this->process === null) {
            $this->process = app(ProcessExecutorInterface::class);
        }
    }

    /**
     * Detect terminal dimensions via tput.
     *
     * Falls back to sensible defaults (30 lines x 120 cols) when tput
     * is not available or returns an error.
     *
     * @return void
     */
    private function detectTtySize()
    {
        list($exitCode, $output) = $this->process->execute('tput lines');
        $this->ttyLines = ($exitCode === 0) ? intval($output) : 30;

        list($exitCode, $output) = $this->process->execute('tput cols');
        $this->ttyCols = ($exitCode === 0) ? intval($output) : 120;

        $this->cwd = dirname(realpath($_SERVER['argv'][0]));
        $this->commandName = basename(realpath($_SERVER['argv'][0]));
        $this->fullExecutablePath = realpath($_SERVER['PHP_SELF']);
    }

    /**
     * Render an error message inside a bordered box.
     *
     * Word-wraps the message to fit the terminal width, then draws a box
     * using asterisks with centred text on a red background.
     *
     * @param string $message
     * @return void
     */
    private function renderErrorBox(string $message)
    {
        $message = wordwrap($message, $this->ttyCols - 10, "\n", true);
        $lines = explode("\n", $message);

        if (count($lines) + 2 > $this->ttyLines) {
            $this->error($message);
            return;
        }

        $boxBound = str_repeat('*', $this->ttyCols);
        $this->error($boxBound);

        foreach ($lines as $line) {
            $this->error('*' . str_pad($line, $this->ttyCols - 2, ' ', STR_PAD_BOTH) . '*');
        }

        $this->error($boxBound);
    }
}
