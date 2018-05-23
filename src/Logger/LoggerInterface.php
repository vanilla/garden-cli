<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger;

/**
 * Interface LoggerInterface
 *
 * The custom logging interface for Garden CLI projects that permits the concept of nesting.
 */
interface LoggerInterface {

    /**
     * Set the maximum indent level to send to the writers.
     *
     * @param int $maxLevel The maximum indent level
     *
     * @return $this
     */
    public function setMaxLevel(int $maxLevel);

    /**
     * Get the max indent level to send to the writers.
     *
     * @return int Returns the maxLevel.
     */
    public function getMaxLevel();

    /**
     * Log a message that designates the beginning of a task.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function begin(string $str);


    /**
     * Log an error message.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function error(string $str);

    /**
     * Log a success message.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function success(string $str);

    /**
     * Log a warning message.
     *
     * @param string $str The message to output.
     *
     * @return $this
     */
    public function warn(string $str);

    /**
     * Log a message.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to force output of the message even if it's past the max depth.
     *
     * @return $this
     */
    public function message(string $str, bool $force = false);

    /**
     * Log a message that represents a task being completed in success.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to force a message past the max level to be output.
     *
     * @return $this
     */
    public function endSuccess(string $str, bool $force = false);

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
    public function endError(string $str);

    /**
     * Log a message that designates a task being completed.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to always output the message even if the task is past the max depth.
     * @param string $logLevel The level/type of log to write.
     *
     * @return $this
     */
    public function end(string $str = '', bool $force =  false, $logLevel = LogLevels::INFO);

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
    public function endHttpStatus(int $httpStatus, bool $force = false);
}
