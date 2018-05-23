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
class ColorizerFormatter implements FormatterInterface {

    /**
     * Wrap text in console color codes, if they are supported.
     *
     * @param string $message The message.
     * @param string $logLevel The level of the message (e.g. SUCCESS, WARNING, ERROR).
     *
     * @return string
     */
    private function colorify(string $message, string $logLevel) {
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
                $prefix = '';
                $suffix = '';
            break;
        };

        return "{$prefix}$message{$suffix}";
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
        $message = $this->colorify($message, $logLevel);
        $duration = $this->colorify((string) $duration, 'duration');

        return [$time, $logLevel, $indentLevel, $message, $duration];
    }
}
