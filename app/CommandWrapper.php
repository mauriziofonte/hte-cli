<?php

namespace Mfonte\HteCli;

use LaravelZero\Framework\Commands\Command;

class CommandWrapper extends Command
{
    private int $ttyLines;
    private int $ttyCols;
    protected bool $runningAsRoot = false;
    protected string $userName;
    protected string $userGroup;
    protected string $userHome;
    protected int $userUid;
    protected int $userGid;

    public function __construct()
    {
        // construct the parent class
        parent::__construct();
    }

    /**
     * The pre-flight stuff that must be done before running ANY command.
     *
     * @return void
     */
    protected function preRun()
    {
        // TAAG on Small Slant
        $this->line("\e[1;97m   __ __ ______ ____      _____ __ _  \e[0m");
        $this->line("\e[1;97m  / // //_  __// __/____ / ___// /(_) \e[0m");
        $this->line("\e[1;97m / _  /  / /  / _/ /___// /__ / // /  \e[0m");
        $this->line("\e[1;97m/_//_/  /_/  /___/      \___//_//_/   \e[0m");
        $this->line("\e[1;97m                                      \e[0m");
        $this->output->writeln("<info>[H]andle [T]est [E]nvironment Cli Tool</info> version <comment>{$this->getApplication()->getVersion()}</comment> by <fg=cyan>Maurizio Fonte</>");
        $this->output->writeln("<bg=red;fg=white;options=bold>WARNING: THIS TOOL IS *NOT* INTENDED FOR LIVE SERVERS.</> Use it only on local/firewalled networks.");
        $this->output->writeln("");

        // pre-flight stuff
        $this->checkEnvironment();
        $this->checkFunctions();
        $this->setTtyParams();
        $this->checkRoot();

        // get the username of the user that is running the script (if we're not root)
        if (!$this->runningAsRoot) {
            $userInfo = posix_getpwuid($_SERVER['SUDO_UID']);
        } else {
            // ask root user to provide a username: this will be the user that will own the configurations
            $impersonateUsername = $this->keepAsking('Enter the username that will own the configurations', "", function ($answer) {
                return !empty($answer);
            });

            $userInfo = posix_getpwnam($impersonateUsername);
            if (empty($userInfo)) {
                $this->criticalError("The user {$impersonateUsername} does not exist.");
                exit(1);
            }
        }

        // fill in the user info
        $this->userName = $userInfo['name'];
        $this->userGroup = posix_getgrgid($userInfo['gid'])['name'];
        $this->userHome = $userInfo['dir'];
        $this->userUid = $userInfo['uid'];
        $this->userGid = $userInfo['gid'];
    }

    /**
     * Prints a message to the terminal with a green background and white text.
     *
     * @param string $message
     *
     * @return void
     */
    protected function criticalError(string $message)
    {
        // wrap the error message so that it fits in the terminal (split in multiple lines)
        $message = wordwrap($message, $this->ttyCols - 10, "\n", true);
        $lines = explode("\n", $message);

        if (count($lines) + 2 > $this->ttyLines) {
            // simply report the error message without any formatting
            $this->info($this->ttyLines);
            $this->error($message);
        } else {
            // wrap the error message in a box with a width of $this->ttyCols, with red background and white text
            $boxBound = str_repeat("*", $this->ttyCols);
            
            // print the box
            $this->error($boxBound);
            foreach ($lines as $line) {
                // horizontally align the text to the center of the box
                $this->error("*" . str_pad($line, $this->ttyCols - 2, " ", STR_PAD_BOTH) . "*");
            }
            $this->error($boxBound);
        }

        // exit with an error code
        exit(1);
    }

    /**
     * Executes a shell command and returns the output and the command retcode.
     *
     * @param string $command
     *
     * @return array - A tuple with the string output and the return code of the command
     */
    protected function shellExecute(string $command) : array
    {
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        return [implode(PHP_EOL, $output), $returnCode];
    }

    /**
     * Asks a question to the user until the answer is valid.
     *
     * @param string $question
     * @param string $default
     * @param callable $validate
     * @param string $invalidAnswerMsg
     *
     * @return string
     */
    protected function keepAsking(string $question, string $default, callable $validate, string $invalidAnswerMsg = 'Invalid input. Please try again.') : string
    {
        while (true) {
            $answer = $this->ask("💡 {$question}", $default);
            $valid = $validate($answer);
            if ($valid) {
                if (is_string($valid)) {
                    // the validator returned a string: this is the "transformed" answer (maybe trimmed, lowercased, etc.)
                    return $valid;
                }

                // fallback: return the answer given by the user
                return $answer;
            }
            $this->error("⛔ {$invalidAnswerMsg}");
        }
    }

    /**
     * Checks if the current environment is supported.
     * If not, terminates execution with an error message.
     *
     * @return void
     */
    private function checkEnvironment()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $this->criticalError("Windows is not supported yet.");
            exit(1);
        }
    }

    /**
     * Checks if the current environment supports some functions that we will use.
     * If not, terminates execution with an error message.
     *
     * @return void
     */
    private function checkFunctions()
    {
        $functions = [
            'exec',
            'posix_getuid',
            'posix_getpwuid',
            'posix_getgrgid'
        ];

        array_map(function ($function) {
            if (!function_exists($function)) {
                $this->criticalError("Your PHP installation does not support {$function}() function. Check that is enabled in your php.ini config.");
                exit(1);
            }
        }, $functions);
    }

    /**
     * Sets the tty parameters (rows and cols) for the current terminal.
     *
     * @return void
     */
    private function setTtyParams()
    {
        // set the current tty rows (the amount of rows in the terminal)
        list($ttyLines, $retval) = $this->shellExecute('tput lines');
        if ($retval != 0) {
            $this->criticalError("Failed to get the current terminal rows.");
            exit(1);
        }
        $this->ttyLines = intval($ttyLines);

        // set the current tty cols (the amount of columns in the terminal)
        list($ttyCols, $retval) = $this->shellExecute('tput cols');
        if ($retval != 0) {
            $this->criticalError("Failed to get the current terminal columns.");
            exit(1);
        }
        $this->ttyCols = intval($ttyCols);
    }

    /**
     * Checks if the current running user is ROOT.
     * If not, terminates execution with an error message.
     *
     * @return void
     */
    private function checkRoot()
    {
        if (posix_getuid() != 0) {
            $this->criticalError("You must be root to run this command.");
            exit(1);
        }

        $uname = (array_key_exists('USER', $_SERVER)) ? $_SERVER['USER'] : null;
        if (empty($uname)) {
            $this->criticalError("Failed to get the current user name.");
        }

        if ($uname !== 'root') {
            if (!array_key_exists('SUDO_UID', $_SERVER) || !array_key_exists('SUDO_GID', $_SERVER)) {
                $this->criticalError("You must run this command with sudo.");
                exit(1);
            }
        } else {
            $this->warn("⚠️ You are running this command as root.");
            $this->warn("⚠️ You will be asked to provide a username that will own the configurations for Apache and PHP-FPM.");
            $this->runningAsRoot = true;
        }
    }
}
