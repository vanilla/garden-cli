<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

/**
 * A helper class to format CLI output in a log-style format.
 */
class LogFormatter {
    /**
     * @var string The date format as passed to {@link strftime()}.
     */
    protected $dateFormat = '[%F %T]';

    /**
     * @var string The end of line string to use.
     */
    protected $eol = PHP_EOL;

    /**
     * @var bool Whether or not to format output.
     */
    protected $formatOutput;

    /**
     * @var bool Whether or not the console is on a new line.
     */
    protected $isNewline = true;

    /**
     * @var int The maximum level deep to output.
     */
    protected $maxLevel = 2;

    /**
     * @var array An array of currently running tasks.
     */
    protected $taskStack = [];

    /**
     * LogFormatter constructor.
     */
    public function __construct() {
        $this->formatOutput = Cli::guessFormatOutput();
    }

    /**
     * Output an error message.
     *
     * When formatting is turned on, error messages are displayed in red. Error messages are always output, even if they
     * are past the maximum display level.
     *
     * @param string $str The message to output.
     * @return LogFormatter Returns `$this` for fluent calls.
     */
    public function error($str) {
        return $this->message($this->formatString($str, ["\033[1;31m", "\033[0m"]), true);
    }

    /**
     * Output a success message.
     *
     * When formatting is turned on, success messages are displayed in green.
     *
     * @param string $str The message to output.
     * @return LogFormatter Returns `$this` for fluent calls.
     */
    public function success($str) {
        return $this->message($this->formatString($str, ["\033[1;32m", "\033[0m"]));
    }

    /**
     * Get the current depth of tasks.
     *
     * @return int Returns the current level.
     */
    protected function currentLevel() {
        return count($this->taskStack) + 1;
    }

    /**
     * Output a message that designates the beginning of a task.
     *
     * @param string $str The message to output.
     * @return $this Returns `$this` for fluent calls.
     */
    public function begin($str) {
        $output = $this->currentLevel() <= $this->getMaxLevel();
        $task = [$str, microtime(true), $output];

        if ($output) {
            if (!$this->isNewline) {
                echo $this->getEol();
                $this->isNewline = true;
            }

            echo $this->messageStr($str, false);
            $this->isNewline = false;
        }

        array_push($this->taskStack, $task);

        return $this;
    }

    /**
     * Output a message that designates a task being completed.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to always output the message even if the task is past the max depth.
     * @return $this Returns `$this` for fluent calls.
     */
    public function end($str, $force = false) {
        // Find the task we are finishing.
        $task = array_pop($this->taskStack);
        if ($task !== null) {
            list($taskStr, $taskTimestamp, $taskOutput) = $task;
            $timespan = microtime(true) - $taskTimestamp;
        } else {
            trigger_error('Called LogFormatter::end() without calling LogFormatter::begin()', E_USER_NOTICE);
        }

        $pastMaxLevel = $this->currentLevel() > $this->getMaxLevel();
        if ($pastMaxLevel) {
            if ($force && isset($taskStr) && isset($taskOutput)) {
                if (!$taskOutput) {
                    // Output the full task string if it hasn't already been output.
                    $str = $taskStr.' '.$str;
                }
                if (!$this->isNewline) {
                    echo $this->getEol();
                    $this->isNewline = true;
                }
            } else {
                return $this;
            }
        }

        if ($this->isNewline) {
            // Output the end message as a normal message.
            $this->message($str, $force);
        } else {
            // Output the end message on the same line.
            echo ' '.$str.$this->getEol();
            $this->isNewline = true;
        }

        return $this;
    }

    /**
     * Output a message that represents a task being completed in success.
     *
     * When formatting is turned on, success messages are output in green.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to force a message past the max level to be output.
     * @return LogFormatter Returns `$this` for fluent calls.
     */
    public function endSuccess($str, $force = false) {
        return $this->end($this->formatString($str, ["\033[1;32m", "\033[0m"]), $force);
    }

    /**
     * Output a message that represents a task being completed in an error.
     *
     * When formatting is turned on, error messages are output in red. Error messages are always output even if they are
     * past the maximum depth.
     *
     * @param string $str The message to output.
     * @return LogFormatter Returns `$this` for fluent calls.
     */
    public function endError($str) {
        return $this->end($this->formatString($str, ["\033[1;31m", "\033[0m"]), true);
    }

    /**
     * Output a message that ends a task with an HTTP status code.
     *
     * This method is useful if you are making a call to an external API as a task. You can end the task by passing the
     * response code to this message.
     *
     * @param int $httpStatus The HTTP status code that represents the completion of a task.
     * @param bool $force Whether or not to force message output.
     * @return $this Returns `$this` for fluent calls.
     * @see LogFormatter::endSuccess(), LogFormatter::endError().
     */
    public function endHttpStatus($httpStatus, $force = false) {
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
     * Output a message.
     *
     * @param string $str The message to output.
     * @param bool $force Whether or not to force output of the message even if it's past the max depth.
     * @return $this Returns `$this` for fluent calls.
     */
    public function message($str, $force = false) {
        $pastMaxLevel = $this->currentLevel() > $this->getMaxLevel();

        if ($pastMaxLevel) {
            if ($force) {
                // Trace down the task list and output everything that hasn't already been output.
                foreach ($this->taskStack as $indent => $task) {
                    list($taskStr, $taskTimestamp, $taskOutput) = $this->taskStack[$indent];
                    if (!$taskOutput) {
                        if (!$this->isNewline) {
                            echo $this->eol;
                            $this->isNewline = true;
                        }
                        echo $this->fullMessageStr($taskTimestamp, $taskStr, $indent, true);
                        $this->taskStack[$indent][2] = true;
                    } else {
                        continue;
                    }
                }
            } else {
                return $this;
            }
        }

        if (!$this->isNewline) {
            echo $this->eol;
            $this->isNewline = true;
        }
        echo $this->messageStr($str, true);
        return $this;
    }

    /**
     * Get whether or not output should be formatted.
     *
     * @return boolean Returns **true** if output should be formatted or **false** otherwise.
     */
    public function getFormatOutput() {
        return $this->formatOutput;
    }

    /**
     * Set whether or not output should be formatted.
     *
     * @param boolean $formatOutput Whether or not to format output.
     * @return LogFormatter Returns `$this` for fluent calls.
     */
    public function setFormatOutput($formatOutput) {
        $this->formatOutput = $formatOutput;
        return $this;
    }

    protected function fullMessageStr($timestamp, $str, $indent = null, $eol = true) {
        if ($indent === null) {
            $indent = $this->currentLevel() - 1;
        }

        if ($indent <= 0) {
            $indentStr = '';
        } elseif ($indent === 1) {
            $indentStr = '- ';
        } else {
            $indentStr = str_repeat('  ', $indent - 1).'- ';
        }

        $result = $indentStr.$str;

        if ($this->getDateFormat()) {
            $result = strftime($this->getDateFormat(), $timestamp).' '.$result;
        }

        if ($eol) {
            $result .= $this->eol;
        }
        return $result;
    }

    /**
     * Create a message string.
     *
     * @param string $str The message to output.
     * @param bool $eol Whether or not to add an EOL.
     * @return string Returns the message.
     */
    protected function messageStr($str, $eol = true) {
        return $this->fullMessageStr(time(), $str, null, $eol);
    }

    /**
     * Format some text for the console.
     *
     * @param string $text The text to format.
     * @param array $wrap The format to wrap in the form ['before', 'after'].
     * @return string Returns the string formatted according to {@link Cli::$format}.
     */
    protected function formatString($text, array $wrap) {
        if ($this->formatOutput) {
            return "{$wrap[0]}$text{$wrap[1]}";
        } else {
            return $text;
        }
    }

    /**
     * Get the maxLevel.
     *
     * @return int Returns the maxLevel.
     */
    public function getMaxLevel() {
        return $this->maxLevel;
    }

    /**
     * @param int $maxLevel
     * @return LogFormatter
     */
    public function setMaxLevel($maxLevel) {
        if ($maxLevel < 0) {
            throw new \InvalidArgumentException("The max level must be greater than zero.", 416);
        }

        $this->maxLevel = $maxLevel;
        return $this;
    }

    /**
     * Get the date format as passed to {@link strftime()}.
     *
     * @return string Returns the date format.
     * @see strftime()
     */
    public function getDateFormat() {
        return $this->dateFormat;
    }

    /**
     * Set the date format as passed to {@link strftime()}.
     *
     * @param string $dateFormat
     * @return LogFormatter Returns `$this` for fluent calls.
     * @see strftime()
     */
    public function setDateFormat($dateFormat) {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    /**
     * Get the end of line string to use.
     *
     * @return string Returns the eol string.
     */
    public function getEol() {
        return $this->eol;
    }

    /**
     * Set the end of line string.
     *
     * @param string $eol The end of line string to use.
     * @return LogFormatter Returns `$this` for fluent calls.
     */
    public function setEol($eol) {
        $this->eol = $eol;
        return $this;
    }
}
