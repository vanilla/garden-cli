<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * A logger that logs to a stream.
 */
class StreamLogger implements LoggerInterface {
    use LoggerTrait;

    /**
     * @var string The line format.
     */
    private $lineFormat = '[{time}] {message}';

    /**
     * @var callable The level formatting function.
     */
    private $levelFormat;

    /**
     * @var callable The time formatting function.
     */
    private $timeFormatter;

    /**
     * @var string The end of line string to use.
     */
    private $eol = PHP_EOL;

    /**
     * @var bool Whether or not to format output.
     */
    private $colorizeOutput;

    /**
     * @var resource The output file handle.
     */
    private $outputHandle;

    /**
     * @var bool Whether or not the console is on a new line.
     */
    private $inBegin;

    /**
     * @var bool Whether or not to show durations for tasks.
     */
    private $showDurations = true;

    /**
     * @var bool Whether or not to buffer begin logs.
     */
    private $bufferBegins = true;

    private $wraps = [
        LogLevel::DEBUG => ["\033[0;37m", "\033[0m"],
        LogLevel::INFO => ['', ''],
        LogLevel::NOTICE => ["\033[1m", "\033[0m"],
        LogLevel::WARNING => ["\033[1;33m", "\033[0m"],
        LogLevel::ERROR => ["\033[1;31m", "\033[0m"],
        LogLevel::CRITICAL => ["\033[1;35m", "\033[0m"],
        LogLevel::ALERT => ["\033[1;35m", "\033[0m"],
        LogLevel::EMERGENCY => ["\033[1;35m", "\033[0m"],
    ];

    /**
     * @var resource Whether or not the default stream was opened.
     */
    private $defaultStream;

    /**
     * LogFormatter constructor.
     *
     * @param mixed $out Either a path or a stream resource for the output.
     */
    public function __construct($out = STDOUT) {
        if (is_string($out)) {
            try {
                $this->defaultStream = $out = fopen($out, 'a+');
            } catch (\Throwable $ex) {
                throw new \InvalidArgumentException($ex->getMessage(), 500);
            }
            if (!is_resource($out)) {
                throw new \InvalidArgumentException("The supplied path could not be opened: $out", 500);
            }
        } elseif (!is_resource($out)) {
            throw new \InvalidArgumentException('The value supplied for $out must be either a stream resource or a path.', 500);
        }

        $this->outputHandle = $out;
        $this->colorizeOutput = Cli::guessFormatOutput($this->outputHandle);
        $this->setTimeFormat('%F %T');
        $this->setLevelFormat(function ($l) {
            return $l;
        });
    }

    /**
     * Set the time formatter.
     *
     * This method takes either a format string for **strftime()** or a callable that must format a timestamp.
     *
     * @param string|callable $format The new format.
     * @return $this
     * @see strftime()
     */
    public function setTimeFormat($format) {
        if (is_string($format)) {
            $this->timeFormatter = function ($t) use ($format): string {
                return strftime($format, $t);
            };
        } else {
            $this->timeFormatter = $format;
        }

        return $this;
    }

    /**
     * Clean up the default stream if it was use.
     */
    public function __destruct() {
        if (is_resource($this->defaultStream)) {
            fclose($this->defaultStream);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array()) {
        if (!isset($this->wraps[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level", 400);
        }

        $msg = $this->replaceContext($message, $context);

        $eol = true;
        $fullLine = true;
        $str = ''; // queue everything in a string to avoid race conditions

        if ($this->bufferBegins()) {
            if (!empty($context[TaskLogger::FIELD_BEGIN])) {
                if ($this->inBegin) {
                    $str .= $this->eol;
                } else {
                    $this->inBegin = true;
                }
                $eol = false;
            } elseif (!empty($context[TaskLogger::FIELD_END]) && strpos($msg, "\n") === false) {
                if ($this->inBegin) {
                    $msg = ' '.$msg;
                    $fullLine = false;
                    $this->inBegin = false;
                }
            } elseif ($this->inBegin) {
                $str .= $this->eol;
                $this->inBegin = false;
            }
        }

        $str .= $this->fullMessageStr($level, $msg, $context, $fullLine);

        if ($eol) {
            $str .= $this->eol;
        }

        if (!is_resource($this->outputHandle) || feof($this->outputHandle)) {
            trigger_error('The StreamLogger output handle is not valid.', E_USER_WARNING);
        } else {
            fwrite($this->outputHandle, $str);
        }
    }

    /**
     * Replace a message format with context information.
     *
     * The message format contains fields wrapped in curly braces.
     *
     * @param string $format The message format to replace.
     * @param array $context The context data.
     * @return string Returns the formatted message.
     */
    private function replaceContext(string $format, array $context): string {
        $msg = preg_replace_callback('`({[^\s{}]+})`', function ($m) use ($context) {
            $field = trim($m[1], '{}');
            if (array_key_exists($field, $context)) {
                return $context[$field];
            } else {
                return $m[1];
            }
        }, $format);
        return $msg;
    }

    /**
     * Whether not to buffer the newline for begins.
     *
     * When logging a begin this setting will buffer the newline and output the end of the task on the same line if possible.
     *
     * @return bool Returns the bufferBegins.
     */
    public function bufferBegins(): bool {
        return $this->bufferBegins;
    }

    /**
     * Format a full message string.
     *
     * @param string $level The logging level.
     * @param string $message The message to format.
     * @param array $context Variable replacements for the message.
     * @param bool $fullLine Whether or not this is a full line message.
     * @return string Returns a formatted message.
     */
    private function fullMessageStr(string $level, string $message, array $context, bool $fullLine = true): string {
        $levelStr = call_user_func($this->getLevelFormat(), $level);

        $timeStr = call_user_func($this->getTimeFormat(), $context[TaskLogger::FIELD_TIME] ?? microtime(true));

        $indent = $context[TaskLogger::FIELD_INDENT] ?? 0;
        if ($indent <= 0) {
            $indentStr = '';
        } else {
            $indentStr = str_repeat('  ', $indent - 1).'- ';
        }

        // Explode on "\n" because the input string may have a variety of newlines.
        $lines = explode("\n", $message);
        if ($fullLine) {
            foreach ($lines as &$line) {
                $line = rtrim($line);
                $line = $this->replaceContext($this->getLineFormat(), [
                    'level' => $levelStr,
                    'time' => $timeStr,
                    'message' => $indentStr.$line
                ]);
            }
        }
        $result = implode($this->getEol(), $lines);

        $wrap = $this->wraps[$level] ?? ['', ''];
        $result = $this->formatString($result, $wrap);

        if (isset($context[TaskLogger::FIELD_DURATION]) && $this->showDurations()) {
            if ($result && !preg_match('`\s$`', $result)) {
                $result .= ' ';
            }

            $result .= $this->formatString($this->formatDuration($context[TaskLogger::FIELD_DURATION]), ["\033[1;34m", "\033[0m"]);
        }

        return $result;
    }

    /**
     * Get the level formatting function.
     *
     * @return callable Returns the levelFormat.
     */
    public function getLevelFormat(): callable {
        return $this->levelFormat;
    }

    /**
     * Set the level formatting function.
     *
     * @param callable $levelFormat The new level format.
     * @return $this
     */
    public function setLevelFormat(callable $levelFormat) {
        $this->levelFormat = $levelFormat;
        return $this;
    }

    /**
     * Get the time format function.
     *
     * @return callable Returns the date format.
     * @see strftime()
     */
    public function getTimeFormat(): callable {
        return $this->timeFormatter;
    }

    /**
     * Get the log line format.
     *
     * The log line format determines how lines are outputted. The line consists of fields enclosed in curly braces and
     * other raw strings. The fields available for the format are the following:
     *
     * - `{level}`: Output the log level.
     * - `{time}`: Output the time of the log.
     * - `{message}`: Output the message string.
     *
     * @return string Returns the lineFormat.
     */
    public function getLineFormat(): string {
        return $this->lineFormat;
    }

    /**
     * Set the log line format.
     *
     * @param string $lineFormat The new line format.
     * @return $this
     */
    public function setLineFormat(string $lineFormat) {
        $this->lineFormat = $lineFormat;
        return $this;
    }

    /**
     * Get the end of line string to use.
     *
     * @return string Returns the eol string.
     */
    public function getEol(): string {
        return $this->eol;
    }

    /**
     * Set the end of line string.
     *
     * @param string $eol The end of line string to use.
     * @return $this
     */
    public function setEol(string $eol) {
        if (strpos($eol, "\n") === false) {
            throw new \InvalidArgumentException('The EOL must include the "\n" character."', 500);
        }

        $this->eol = $eol;
        return $this;
    }

    /**
     * Format some text for the console.
     *
     * @param string $text The text to format.
     * @param string[] $wrap The format to wrap in the form ['before', 'after'].
     * @return string Returns the string formatted according to {@link Cli::$format}.
     */
    private function formatString(string $text, array $wrap): string {
        if ($this->colorizeOutput()) {
            return "{$wrap[0]}$text{$wrap[1]}";
        } else {
            return $text;
        }
    }

    /**
     * Get the showDurations.
     *
     * @return boolean Returns the showDurations.
     */
    public function showDurations(): bool {
        return $this->showDurations;
    }

    /**
     * Format a time duration.
     *
     * @param float $duration The duration in seconds and fractions of a second.
     * @return string Returns the duration formatted for humans.
     * @see microtime()
     */
    private function formatDuration(float $duration): string {
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
     * Whether or not to format console output.
     *
     * @return bool Returns the format output setting.
     */
    public function colorizeOutput(): bool {
        return $this->colorizeOutput;
    }

    /**
     * Set the showDurations.
     *
     * @param bool $showDurations
     * @return $this
     */
    public function setShowDurations(bool $showDurations) {
        $this->showDurations = $showDurations;
        return $this;
    }

    /**
     * Set whether not to buffer the newline for begins.
     *
     * @param bool $bufferBegins The new value.
     * @return $this
     */
    public function setBufferBegins(bool $bufferBegins) {
        $this->bufferBegins = $bufferBegins;
        return $this;
    }

    /**
     * Set whether or not to format console output.
     *
     * @param bool $colorizeOutput The new value.
     * @return $this
     */
    public function setColorizeOutput(bool $colorizeOutput) {
        $this->colorizeOutput = $colorizeOutput;
        return $this;
    }
}
