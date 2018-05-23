<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Formatter;

/**
 * Class DateFormatter
 */
class DateFormatter implements FormatterInterface {

    /**
     * @var string The date format as passed to {@link strftime()}.
     */
    protected $dateFormat = '[%F %T]';

    /**
     * DateFormatter constructor.
     *
     * @param string $dateFormat The strftime()-compatible format.
     */
    public function __construct(string $dateFormat = '[%F %t]') {
        $this->dateFormat = $dateFormat;
    }

    /**
     * Get the date format as passed to {@link strftime()}.
     *
     * @return string Returns the strftime()-compatible format.
     */
    public function getDateFormat() {
        return $this->dateFormat;
    }

    /**
     * Format one or more of the components of the log entry.
     *
     * @param int|string $time The time of the log entry.
     * @param string $logLevel The level of the message.
     * @param int $indentLevel The nesting level of the message.
     * @param string $message The message.
     * @param float|null|string $duration The duration to add to the message.
     *
     * @return array
     */
    public function format($time, string $logLevel, int $indentLevel, string $message, $duration) {
        $time = strftime($this->getDateFormat(), $time);

        return [$time, $logLevel, $indentLevel, $message, $duration];
    }
}
