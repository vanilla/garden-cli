<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Formatter;

use Garden\Cli\Logger\LogLevels;

/**
 * Class ColorStreamFormatter
 */
class ColorStreamFormatter implements FormatterInterface {

    /**
     * @var string The date format as passed to {@link strftime()}.
     */
    protected $dateFormat = '[%F %T]';

    /**
     * @var bool Whether or not to show durations for tasks.
     */
    protected $showDurations = true;


    /**
     * Set the date format as passed to {@link strftime()}.
     *
     * @param string $dateFormat The strftime()-compatible format.
     *
     * @return $this
     */
    public function setDateFormat(string $dateFormat) {
        $this->dateFormat = $dateFormat;

        return $this;
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
     * Set the showDurations.
     *
     * @param boolean $showDurations Flag to indicate if durations should be displayed.
     *
     * @return $this
     */
    public function setShowDurations(bool $showDurations) {
        $this->showDurations = $showDurations;

        return $this;
    }

    /**
     * Get the showDurations.
     *
     * @return boolean Returns the showDurations.
     */
    public function getShowDurations() {
        return $this->showDurations;
    }

    /**
     * Format a time duration.
     *
     * @param float $duration The duration in seconds and fractions of a second.
     *
     * @return string Returns the duration formatted for humans.
     */
    public function formatDuration(float $duration) {
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

    /**
     * Wrap text in console color codes, if they are supported.
     *
     * @param string $message The message.
     * @param string $logLevel The level of the message (e.g. SUCCESS, WARNING, ERROR).
     *
     * @return string
     */
    protected function colorify(string $message, string $logLevel) {
        $suffix = "\033[0m";
        switch ($logLevel) {
            case LogLevels::SUCCESS:
                $prefix = "\033[1;32m";
                break;
            case LogLevels::WARNING:
                $prefix = "\033[1;33m";
                break;
            case LogLevels::ERROR:
                $prefix = "\033[1;31m";
                break;
            case 'duration': // edge case: not a log level, but it allows for function re-use
                $prefix = "\033[1;34m";
                break;
            case LogLevels::INFO:
            default:
                $suffix = '';
                $prefix = '';
            break;
        };

        // we only wrap with color if it is supported
        return "{$prefix}$message{$suffix}";
    }

    /**
     * Format a message.
     *
     * @param int $time The timestamp of the log.
     * @param string $logLevel The level of the message.
     * @param int $indentLevel The nesting level of the message.
     * @param string $message The message.
     * @param float|null $duration The duration to add to the message.
     *
     * @return array
     */
    public function format($time, string $logLevel, int $indentLevel, string $message, $duration) {
        $time = strftime($this->getDateFormat(), $time);
        $message = $this->colorify($message, $logLevel);

        if ($this->showDurations && ! is_null($duration)) {
            $duration = $this->formatDuration($duration);
            $duration = $this->colorify($duration, 'duration');
        } else {
            $duration = '';
        }

        return [$time, $logLevel, $indentLevel, $message, $duration];
    }
}
