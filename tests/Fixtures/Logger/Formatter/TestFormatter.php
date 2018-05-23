<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures\Logger\Formatter;

use Garden\Cli\Logger\Formatter\FormatterInterface;

/**
 * Implementation of a log formatter for testing use only.
 */
class TestFormatter implements FormatterInterface {

    /**
     * Set the date format as passed to {@link strftime()}.
     *
     * @param string $dateFormat The strftime()-compatible format.
     *
     * @return $this Returns `$this` for fluent calls.
     */
    public function setDateFormat(string $dateFormat) {
        return $this;
    }

    /**
     * Get the date format as passed to {@link strftime()}.
     *
     * @return string Returns the strftime()-compatible format.
     */
    public function getDateFormat() {
        return '[%F %T]';
    }


    /**
     * Set the showDurations.
     *
     * @param boolean $showDurations Flag to indicate if durations should be displayed.
     *
     * @return $this Returns `$this` for fluent calls.
     */
    public function setShowDurations(bool $showDurations) {
        return $this;
    }

    /**
     * Get the showDurations.
     *
     * @return boolean Returns the showDurations.
     */
    public function getShowDurations() {
        return true;
    }


    /**
     * Format a time duration.
     *
     * @param float $duration The duration in seconds and fractions of a second.
     *
     * @return string Returns the duration formatted for humans.
     */
    public function formatDuration(float $duration) {
        return "{$duration}";
    }

    /**
     * Format a message.
     *
     * @param int|string $time The time of the log entry.
     * @param string $logLevel The level of the message (e.g. SUCCESS, WARNING, ERROR).
     * @param int $indentLevel The nesting level of the message.
     * @param string $message The message.
     * @param float|null|string $duration The duration to add to the message.
     *
     * @return array
     */
    public function format($time, string $logLevel, int $indentLevel, string $message, $duration) {
        // as a test method just pass through exactly what we received
        return func_get_args();
    }
}
