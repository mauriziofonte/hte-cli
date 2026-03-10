<?php

namespace Mfonte\HteCli\Exceptions;

/**
 * Exception thrown when a critical error occurs during command execution.
 *
 * Replaces direct exit(1) calls to make error flow testable.
 * Caught by CommandWrapper::execute() to render an error box and return exit code 1.
 */
class CriticalErrorException extends \RuntimeException
{
}
