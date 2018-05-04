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
     * Set the date format as passed to {@link strftime()}.
     *
     * @param string $dateFormat The strftime()-compatible format.
     *
     * @return $this
     */
    public function setDateFormat(string $dateFormat);

    /**
     * Get the date format as passed to {@link strftime()}.
     *
     * @return string Returns the strftime()-compatible format.
     */
    public function getDateFormat();


    /**
     * Set the showDurations.
     *
     * @param boolean $showDurations Flag to indicate if durations should be displayed.
     *
     * @return $this
     */
    public function setShowDurations(bool $showDurations);

    /**
     * Get the showDurations.
     *
     * @return boolean Returns the showDurations.
     */
    public function getShowDurations();


    /**
     * Format a time duration.
     *
     * @param float $duration The duration in seconds and fractions of a second.
     *
     * @return string Returns the duration formatted for humans.
     */
    public function formatDuration(float $duration);

    /**
     * Format a message.
     *
     * @param int $timestamp The timestamp of the log.
     * @param string $logLevel The level of the message (e.g. SUCCESS, WARNING, ERROR).
     * @param int $indentLevel The nesting level of the message.
     * @param string $message The message.
     * @param float|null $duration The duration to add to the message.
     *
     * @return string
     */
    public function format(int $timestamp, string $logLevel, int $indentLevel, string $message, $duration);
}
