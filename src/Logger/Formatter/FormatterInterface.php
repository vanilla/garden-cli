<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Formatter;

/**
 * Interface FormatterInterface.
 *
 * The custom interface for string formatters used by Garden loggers.
 */
interface FormatterInterface {

    /**
     * Format one or more of the components of the log entry.
     *
     * @param int|string $time The time of the log entry.
     * @param string $logLevel The level of the message (e.g. SUCCESS, WARNING, ERROR).
     * @param int $indentLevel The nesting level of the message.
     * @param string $message The message.
     * @param float|null|string $duration The duration to add to the message.
     *
     * @return array
     */
    public function format($time, string $logLevel, int $indentLevel, string $message, $duration);
}
