<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger;

/**
 * Class LogLevels
 *
 * The Garden logger has its own version of log levels different from RFC 5424 and RFC 3164.
 */
class LogLevels {
    const INFO  = 'info'; // is also the "default" level
    const SUCCESS  = 'success';
    const ERROR    = 'error';
    const WARNING  = 'warning';
}
