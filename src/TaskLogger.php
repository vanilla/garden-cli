<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * A helper class to format CLI output in a log-style format.
 */
class TaskLogger implements LoggerInterface {
    use LoggerTrait;

    const FIELD_INDENT = '_indent';
    const FIELD_TIME = '_time';
    const FIELD_BEGIN = '_begin';
    const FIELD_END = '_end';
    const FIELD_DURATION = '_duration';
    const FIELD_LEVEL = '_level';

    private static $levels = [
        LogLevel::DEBUG,
        LogLevel::INFO,
        LogLevel::NOTICE,
        LogLevel::WARNING,
        LogLevel::ERROR,
        LogLevel::CRITICAL,
        LogLevel::ALERT,
        LogLevel::EMERGENCY,
    ];
    /**
     * @var string The minimum level deep to output.
     */
    private $minLevel = LogLevel::INFO;
    /**
     * @var array An array of currently running tasks.
     */
    private $taskStack = [];
    /**
     * @var LoggerInterface The logger to ultimately log the information to.
     */
    private $logger;

    /**
     * TaskLogger constructor.
     *
     * @param LoggerInterface $logger The logger to ultimately log the information to.
     * @param string $minLevel The minimum error level that will be logged. One of the **LogLevel** constants.
     */
    public function __construct(LoggerInterface $logger = null, $minLevel = LogLevel::INFO) {
        if ($logger === null) {
            $logger = new StreamLogger();
        }
        $this->logger = $logger;
        $this->setMinLevel($minLevel);
    }

    /**
     * Output a debug message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginDebug(string $message, array $context = []) {
        return $this->begin(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Output a message that designates the beginning of a task.
     *
     * @param string $level
     * @param string $message The message to output.
     * @param array $context The log context.
     * @return $this
     */
    public function begin(string $level, string $message, array $context = []) {
        $output = $this->compareLevel($level, $this->getMinLevel()) >= 0;
        $context = [self::FIELD_BEGIN => true] + $context + [self::FIELD_TIME => microtime(true)];
        $task = [$level, $message, $context, $output];

        if ($output) {
            $this->log($level, $message, $context);
        }

        array_push($this->taskStack, $task);

        return $this;
    }

    /**
     * Compare two log levels.
     *
     * @param string $a The first log level to compare.
     * @param string $b The second log level to compare.
     * @return int Returns -1, 0, or 1.
     */
    private function compareLevel(string $a, string $b): int {
        $i = array_search($a, static::$levels);
        if ($i === false) {
            throw new InvalidArgumentException("Log level is invalid: $a", 500);
        }

        return $i <=> array_search($b, static::$levels);
    }

    /**
     * Get the min level.
     *
     * @return string Returns the maxLevel.
     */
    public function getMinLevel(): string {
        return $this->minLevel;
    }

    /**
     * Set the minimum error level.
     *
     * @param string $minLevel One of the PSR logger levels.
     * @return $this
     */
    public function setMinLevel(string $minLevel) {
        $this->minLevel = $minLevel;
        return $this;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []) {
        if ($this->compareLevel($level, $this->getMinLevel()) >= 0) {
            $this->outputTaskStack();

            // Increase the level begin tasks if this level is higher.
            foreach ($this->taskStack as &$task) {
                if ($this->compareLevel($level, $task[0]) > 0) {
                    $task[0] = $level;
                }
            }

            $this->logInternal($level, $message, [self::FIELD_INDENT => $this->currentIndent()] + $context);
        }
    }

    /**
     * Output the task stack.
     *
     * @return void
     */
    private function outputTaskStack(): void {
        foreach ($this->taskStack as $indent => &$task) {
            list($taskLevel, $taskMessage, $taskContext, $taskOutput) = $task;
            if (!$taskOutput) {
                $this->logInternal($taskLevel, $taskMessage, [self::FIELD_INDENT => $indent] + $taskContext);

                $task[3] = true; // mark task as outputted
            }
        }
    }

    /**
     * Internal log implementation with less error checking.
     *
     * @param string $level The log level.
     * @param string $message The log message.
     * @param array $context The log context.
     *
     * @return void
     */
    private function logInternal(string $level, string $message, array $context = []): void {
        $context = $context + [self::FIELD_INDENT => $this->currentIndent(), self::FIELD_TIME => microtime(true)];
        $this->logger->log($level, $message, $context);
    }

    /**
     * Get the current depth of tasks.
     *
     * @return int Returns the current level.
     */
    private function currentIndent() {
        return count($this->taskStack);
    }

    /**
     * Output an info message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginInfo(string $message, array $context = []) {
        return $this->begin(LogLevel::INFO, $message, $context);
    }

    /**
     * Output a notice message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginNotice(string $message, array $context = []) {
        return $this->begin(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Output a warning message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginWarning(string $message, array $context = []) {
        return $this->begin(LogLevel::WARNING, $message, $context);
    }

    /**
     * Output an error message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginError(string $message, array $context = []) {
        return $this->begin(LogLevel::ERROR, $message, $context);
    }

    /**
     * Output a critical message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginCritical(string $message, array $context = []) {
        return $this->begin(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Output an alert message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginAlert(string $message, array $context = []) {
        return $this->begin(LogLevel::ALERT, $message, $context);
    }

    /**
     * Output an emergency message that designates the beginning of a task.
     *
     * @param string $message The message to output.
     * @param array $context Context variables to pass to the message.
     * @return $this
     */
    public function beginEmergency(string $message, array $context = []) {
        return $this->begin(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Output a message that ends a task with an HTTP status code.
     *
     * This method is useful if you are making a call to an external API as a task. You can end the task by passing the
     * response code to this message.
     *
     * @param int $httpStatus The HTTP status code that represents the completion of a task.
     * @return $this
     */
    public function endHttpStatus(int $httpStatus) {
        $statusStr = sprintf('%03d', $httpStatus);

        if ($httpStatus == 0 || $httpStatus >= 500) {
            $this->end($statusStr, [self::FIELD_LEVEL => LogLevel::CRITICAL]);
        } elseif ($httpStatus >= 400) {
            $this->endError($statusStr);
        } else {
            $this->end($statusStr);
        }

        return $this;
    }

    /**
     * Output a message that designates a task being completed.
     *
     * @param string $message The message to output.
     * @param array $context Context for the log. There are a few special fields that can be given with the context.
     *
     * - **TaskLogger::FIELD_TIME**: Specify a specific timestamp for the log.
     * - **TaskLogger::FIELD_LEVEL**: Override the level of the end log. Otherwise the level of the beg log is used.
     *
     * @return $this Returns `$this` for fluent calls.
     */
    public function end(string $message = '', array $context = []) {
        $context = [self::FIELD_INDENT => $this->currentIndent() - 1, self::FIELD_END => true] + $context + [self::FIELD_TIME => microtime(true)];
        $level = $context[self::FIELD_LEVEL] ?? null;

        // Find the task we are finishing.
        $task = end($this->taskStack);
        if ($task !== false) {
            list($taskLevel, $taskMessage, $taskContext, $taskOutput) = $task;
            $context[self::FIELD_DURATION] = $context[self::FIELD_TIME] - $taskContext[self::FIELD_TIME];
            $level = $level ?: $taskLevel;
        } else {
            trigger_error('Called TaskLogger::end() without calling TaskFormatter::begin()', E_USER_NOTICE);
        }
        $level = $level ?: LogLevel::INFO;

        $output = $this->compareLevel($level, $this->getMinLevel()) >= 0;
        if ($output) {
            $this->logInternal($level, $message, $context);
        }
        array_pop($this->taskStack);

        return $this;
    }

    /**
     * Output a message that represents a task being completed in an error.
     *
     * When formatting is turned on, error messages are output in red.
     *
     * @param string $message The message to output.
     * @param array $context The log context.
     * @return $this
     */
    public function endError(string $message, array $context = []) {
        return $this->end($message, [self::FIELD_LEVEL => LogLevel::ERROR] + $context);
    }
}
