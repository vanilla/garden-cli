<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Formatter;

/**
 * Class DurationFormatter
 */
class DurationFormatter implements FormatterInterface {

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
        $duration = $this->formatDuration($duration);

        return [$time, $logLevel, $indentLevel, $message, $duration];
    }

    /**
     * Format a time duration.
     *
     * @param float $duration The duration in seconds and fractions of a second.
     *
     * @return string Returns the duration formatted for humans.
     */
    private function formatDuration(float $duration) {
        if ($duration < 1.0e-3) {
            $n = number_format($duration * 1.0e6, 0);
            $sx = 'μs';
        } elseif ($duration < 1) {
            $n = number_format($duration * 1000, 0);
            $sx = 'ms';
        } elseif ($duration < 60) {
            $n = number_format($duration, 1);
            $sx = 's';
        } elseif ($duration < 3600) {
            $n = number_format($duration / 60, 1);
            $sx = 'm';
        } elseif ($duration < 86400) {
            $n = number_format($duration / 3600, 1);
            $sx = 'h';
        } else {
            $n = number_format($duration / 86400, 1);
            $sx = 'd';
        }

        $result = rtrim($n, '0.').$sx;
        return $result;
    }
}
