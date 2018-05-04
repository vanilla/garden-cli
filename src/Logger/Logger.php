<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger;

use Garden\Cli\Logger\Writer\WriterInterface;

/**
 * Garden logger that accepts multiple writers.
 */
class Logger implements LoggerInterface {

    /**
     * @var LoggerInterface[] An array of writers.
     */
    protected $writers = [];

    /**
     * @var int The max indent level to send to the writers.
     */
    protected $maxLevel = 2;

    /**
     * @var array An array of currently running tasks.
     */
    protected $taskStack = [];

    /**
     * Add a writer.
     *
     * @param WriterInterface $writer A writer to use.
     *
     * @return $this
     */
    public function addWriter(WriterInterface $writer) {
        array_push($this->writers, $writer);

        return $this;
    }

    /**
     * Set the maximum indent level to send to the writers.
     *
     * @param int $maxLevel The maximum indent level
     *
     * @return $this
     */
    public function setMaxLevel(int $maxLevel) {
        if ($maxLevel < 0) {
            throw new \InvalidArgumentException("The max level must be greater than zero.", 416);
        }

        $this->maxLevel = $maxLevel;

        return $this;
    }

    /**
     * Get the max indent level to send to the writers.
     *
     * @return int Returns the maxLevel.
     */
    public function getMaxLevel() {
        return $this->maxLevel;
    }

    /**
     * Log a message that designates the beginning of a task.
     *
     * @param string $str The message to output.
     * @return $this
     */
    public function begin(string $str) {
        $indentLevel = count($this->taskStack) + 1;
        $task = [$str, microtime(true), $indentLevel];

        array_push($this->taskStack, $task);

        // if the current indent level is less than the max, we trigger the logging behaviour
        if ($indentLevel <= $this->getMaxLevel()) {
            $this->write(time(), LogLevels::INFO, $str, $indentLevel);
        }

        return $this;
    }

    /**
     * Log an error message.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function error(string $str) {
        $indentLevel = count($this->taskStack);
        $this->write(time(), LogLevels::ERROR, $str, $indentLevel);

        return $this;
    }

    /**
     * Log a success message.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function success(string $str) {
        $indentLevel = count($this->taskStack);
        $this->write(time(), LogLevels::SUCCESS, $str, $indentLevel);

        return $this;
    }

    /**
     * Log a warning message.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function warn(string $str) {
        $indentLevel = count($this->taskStack);
        $this->write(time(), LogLevels::WARNING, $str, $indentLevel);

        return $this;
    }

    /**
     * Log an info message.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to force output of the message even if it's past the max depth.
     *
     * @return $this
     */
    public function message(string $str, bool $force = false) {
        $indentLevel = count($this->taskStack);

        if ($indentLevel > $this->getMaxLevel()) {

            // if not forced we drop the message
            if (!$force) {
                return $this;
            }

            // output everything that hasn't been output so far
            foreach ($this->taskStack as $task) {
                list($taskStr, $taskTimestamp, $taskIndentLevel) = $task;
                if ($taskIndentLevel > $this->getMaxLevel()) {
                  $this->write($taskTimestamp, LogLevels::INFO, $taskStr, $taskIndentLevel);
                }
            }
        }

        $this->write(time(), LogLevels::INFO, $str, $indentLevel);

        return $this;
    }

    /**
     * Log a message that represents a task being completed in success.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to force a message past the max level to be output.
     *
     * @return $this
     */
    public function endSuccess(string $str, bool $force = false) {
        return $this->end($str, $force, LogLevels::SUCCESS);
    }

    /**
     * Log a message that represents a task being completed in an error.
     *
     * When formatting is turned on, error messages are output in red. Error messages are always output even if they are
     * past the maximum depth.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function endError(string $str) {
        return $this->end($str, true, LogLevels::ERROR);
    }

    /**
     * Log a message that designates a task being completed.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to always output the message even if the task is past the max depth.
     * @param string $logLevel The level/type of log to write.
     *
     * @return $this
     */
    public function end(string $str = '', bool $force =  false, $logLevel = LogLevels::INFO) {
        // get the task we are finishing (there has to be one)
        $task = array_pop($this->taskStack);
        if (is_null($task)) {
            trigger_error('Called Logger::end() without calling Logger::begin()', E_USER_NOTICE);
        } else {
            list($taskStr, $taskTimestamp, $indentLevel) = $task;
            $duration = microtime(true) - $taskTimestamp;

            if (count($this->taskStack) >= $this->getMaxLevel()) {
                if (!$force || !isset($taskStr) || $indentLevel >= $this->getMaxLevel()) {
                    return $this;
                }
            }

            // update the $str so we prepend the original task $str to it
            $str = trim($taskStr.' '.$str);
        }

        return $this->write(time(), $logLevel, $str, $indentLevel || 0, $duration || null);
    }

    /**
     * Log a message that ends a task with an HTTP status code.
     *
     * @param int $httpStatus The HTTP status code that represents the completion of a task.
     * @param bool $force Whether or not to force message output.
     *
     * @return $this
     *
     * @see LogFormatter::endSuccess(), LogFormatter::endError().
     */
    public function endHttpStatus(int $httpStatus, bool $force = false) {
        $statusStr = sprintf('%03d', $httpStatus);

        if ($httpStatus == 0 || $httpStatus >= 400) {
            $this->endError($statusStr);
        } elseif ($httpStatus >= 200 && $httpStatus < 300) {
            $this->endSuccess($statusStr, $force);
        } else {
            $this->end($statusStr, $force);
        }

        return $this;
    }

    /**
     * Write the stream.
     *
     * @param int $timestamp The unix timestamp of the log.
     * @param string $logLevel The level of the message (e.g. SUCCESS, WARNING, ERROR).
     * @param int $indentLevel The nesting level of the message.
     * @param string $message The message.
     * @param float|null $duration The duration to add to the message.
     *
     * @return $this
     */
    public function write(int $timestamp, string $logLevel, string $message, $indentLevel = 0, $duration = null) {
        foreach($this->writers as $writer) {
            $writer->write($timestamp, $logLevel, $message, $indentLevel, $duration);
        }

        return $this;
    }
}
